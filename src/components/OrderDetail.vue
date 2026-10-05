<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import Modal from './Modal.vue'
import { all, command, post, upload, message } from '../api'
import {
  t,
  vehicleLabel,
  orderNumber,
  duration,
  total,
  stamp,
  statusText,
  localInput,
} from '../format'
import type {
  Order,
  Vehicle,
  Employee,
  Active,
  Interval,
  Part,
  Defect,
  Attachment,
  Snippet,
} from '../types'
const printReport = () => window.print()
const props = defineProps<{
  order: Order
  vehicle?: Vehicle
  master: boolean
  employees: Employee[]
}>()
const emit = defineEmits<{ close: []; changed: [] }>()
const current = ref(props.order),
  intervals = ref<Interval[]>([]),
  parts = ref<Part[]>([]),
  defects = ref<Defect[]>([]),
  attachments = ref<Attachment[]>([]),
  snippets = ref<Snippet[]>([]),
  active = ref<Active[]>([])
const employee = ref(''),
  note = ref(''),
  selectedSteps = ref<string[]>([]),
  reason = ref('Pause'),
  resolved = ref<string[]>([]),
  busy = ref(false),
  error = ref(''),
  notice = ref('')
const partForm = ref(false),
  partTitle = ref(''),
  quantity = ref(1),
  partNumber = ref(''),
  partNote = ref(''),
  partPriority = ref('normal'),
  partFile = ref<File | null>(null)
const correcting = ref<Interval | null>(null),
  correctionStart = ref(''),
  correctionEnd = ref(''),
  correctionReason = ref(''),
  changeReason = ref('')
const running = computed(() => active.value.find((a) => a.order_id === current.value.id))
const elapsed = computed(() => total(intervals.value))
const employeeName = (id: string) =>
  props.employees.find((e) => e.id === id)?.name ?? t('Mitarbeiter')
async function load() {
  const id = current.value.id
  const values = await Promise.all([
    all('intervals', { order_id: id }),
    all('parts', { order_id: id }),
    all('attachments', { order_id: id }),
    all('active'),
    all('snippets'),
    props.master ? all('defects', { order_id: id }) : Promise.resolve([]),
  ])
  ;[intervals.value, parts.value, attachments.value, active.value, snippets.value, defects.value] =
    values
}
onMounted(async () => {
  try {
    await load()
  } catch (e) {
    error.value = message(e)
  }
})
async function act(action: string) {
  if (busy.value) return
  notice.value = ''
  busy.value = true
  error.value = ''
  try {
    current.value = await command<Order>(`/api/v1/orders/${current.value.id}/${action}`, {
      version: current.value.version,
      employee_id: employee.value,
      note: note.value,
      steps: selectedSteps.value,
      reason: ['rework', 'cancel'].includes(action) ? changeReason.value : reason.value,
      resolved_defects: resolved.value,
    })
    employee.value = ''
    note.value = ''
    selectedSteps.value = []
    emit('changed')
    notice.value =
      (
        {
          start: t('Arbeit gestartet. Die Zeit läuft – Sie können jetzt zum Fahrzeug gehen.'),
          pause: t('Auftrag pausiert. Die Arbeitszeit ist gespeichert und läuft nicht weiter.'),
          finish: t(
            'Fertigmeldung gespeichert. Die Zeit ist gestoppt; der Meister prüft den Auftrag.',
          ),
          approve: t('Auftrag geprüft und abgeschlossen.'),
          release: t('Auftrag freigegeben und am Werkstatt-Tablet sichtbar.'),
          rework: t('Auftrag zur Nacharbeit wieder geöffnet.'),
          cancel: t('Auftrag storniert. Die bisherigen Einträge bleiben erhalten.'),
        } as Record<string, string>
      )[action] ?? t('Änderung gespeichert.')
    if (props.master || current.value.status !== 'completed') await load()
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function requestPart(pause: boolean) {
  if (busy.value) return
  notice.value = ''
  busy.value = true
  error.value = ''
  try {
    const p = await command<Part>('/api/v1/actions/part-request', {
      order_id: current.value.id,
      employee_id: employee.value,
      title: partTitle.value,
      quantity: quantity.value,
      part_number: partNumber.value,
      priority: partPriority.value,
      note: partNote.value,
      pause,
    })
    notice.value = pause
      ? t('Teileanforderung gespeichert. Der Auftrag ist pausiert; die Zeit läuft nicht weiter.')
      : t('Teileanforderung gespeichert. Die Arbeitszeit läuft weiter.')
    if (partFile.value) {
      try {
        await upload('/api/v1/attachments', partFile.value, {
          order_id: current.value.id,
          part_id: p.id,
        })
      } catch (e) {
        notice.value += ' ' + t('Foto fehlgeschlagen: ') + message(e)
      }
    }
    partForm.value = false
    employee.value = ''
    await refreshOrder()
    emit('changed')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function refreshOrder() {
  const rows = await all('orders', { vehicle_id: current.value.vehicle_id })
  const updated = rows.find((x) => x.id === current.value.id)
  if (updated) current.value = updated
  await load()
}
async function partStatus(p: Part, status: string) {
  busy.value = true
  try {
    await post('/api/v1/actions/part-status', { data: { id: p.id, version: p.version, status } })
    await refreshOrder()
    emit('changed')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
function correct(i: Interval) {
  correcting.value = i
  correctionStart.value = localInput(i.started)
  correctionEnd.value = localInput(i.ended)
  correctionReason.value = ''
}
async function saveCorrection() {
  if (!correcting.value) return
  busy.value = true
  try {
    await post('/api/v1/actions/time-correct', {
      data: {
        id: correcting.value.id,
        version: correcting.value.version,
        started: Math.floor(new Date(correctionStart.value).getTime() / 1000),
        ended: Math.floor(new Date(correctionEnd.value).getTime() / 1000),
        reason: correctionReason.value,
      },
    })
    correcting.value = null
    await load()
    emit('changed')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function download(a: Attachment) {
  try {
    const axios = (await import('@nextcloud/axios')).default
    const { url } = await import('../api')
    const r = await axios.get(url(`/api/v1/attachments/${a.id}`), { responseType: 'blob' })
    const href = URL.createObjectURL(r.data)
    const link = document.createElement('a')
    link.href = href
    link.download = a.name
    link.click()
    setTimeout(() => URL.revokeObjectURL(href), 1000)
  } catch (e) {
    error.value = message(e)
  }
}
async function addFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  busy.value = true
  try {
    await upload('/api/v1/attachments', file, { order_id: current.value.id })
    await refreshOrder()
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
function setPartFile(event: Event) {
  partFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}
async function reload() {
  busy.value = true
  try {
    await refreshOrder()
    error.value = ''
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <Modal :title="orderNumber(current.id) + ' · ' + current.title" @close="!busy && emit('close')">
    <div class="wm-form wm-printable">
      <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
      <p v-if="notice" role="status" class="wm-success wm-no-print">{{ notice }}</p>
      <div v-if="current.status === 'review'" class="wm-guide wm-no-print">
        <strong>{{ t('Die Arbeit ist fertig gemeldet') }}</strong>
        <p>
          {{
            master
              ? t(
                  'Prüfen Sie das Arbeitsprotokoll und die Teileanforderungen. Bestätigen Sie unten, welche Mängel behoben sind, und schließen Sie den Auftrag ab.',
                )
              : t(
                  'Der Meister prüft den Auftrag. Ihre Arbeitszeit ist bereits gestoppt. Sie können einen anderen offenen Auftrag auswählen.',
                )
          }}
        </p>
      </div>
      <div class="wm-detail-heading">
        <strong>{{ vehicleLabel(vehicle) }}</strong><span class="wm-badge" :data-status="current.status">{{
          statusText(current.status)
        }}</span>
      </div>
      <p class="wm-preserve">{{ current.description }}</p>
      <div class="wm-three">
        <div>
          <small>{{ t('Sollzeit') }}</small><strong>{{ current.minutes ? current.minutes + ' min' : '–' }}</strong>
        </div>
        <div>
          <small>{{ t('Geplanter Beginn') }}</small><strong>{{ stamp(current.planned_start) }}</strong>
        </div>
        <div>
          <small>{{ t('Fertig bis') }}</small><strong>{{ stamp(current.due_at) }}</strong>
        </div>
      </div>
      <ul v-if="current.steps.length">
        <li v-for="(s, n) in current.steps" :key="n">{{ s }}</li>
      </ul>
      <p v-if="current.pause_reason">
        <strong>{{ t('Pausengrund') }}:</strong> {{ current.pause_reason }}
      </p>
      <fieldset v-if="['open', 'paused', 'active'].includes(current.status)" class="wm-no-print">
        <legend>{{ t('Ihre Arbeit an diesem Auftrag') }}</legend>
        <p class="wm-muted">
          {{
            current.status === 'active'
              ? t(
                  'Wählen Sie Ihren Namen, haken Sie erledigte Schritte ab und ergänzen Sie bei Bedarf eine Notiz. Danach pausieren oder fertig melden.',
                )
              : t(
                  'Wählen Sie zuerst Ihren eigenen Namen. Die Zeit beginnt erst, wenn Sie „Arbeit starten“ oder „Arbeit fortsetzen“ drücken.',
                )
          }}
        </p>
        <label>{{ t('Wer arbeitet an diesem Auftrag?')
          }}<select v-model="employee">
            <option value="">{{ t('Bitte auswählen') }}</option>
            <option v-for="e in employees.filter((x) => !x.archived)" :key="e.id" :value="e.id">
              {{ e.name
              }}{{ active.some((a) => a.employee_id === e.id) ? ' · ' + t('beschäftigt') : '' }}
            </option>
          </select></label>
        <p v-if="!employee" class="wm-inline-hint">
          {{
            t(
              'Bitte zuerst Ihren Namen auswählen. Danach werden die passenden Schaltflächen freigeschaltet.',
            )
          }}
        </p>
        <p
          v-else-if="active.some((a) => a.employee_id === employee && a.order_id !== current.id)"
          class="wm-inline-hint"
        >
          {{
            t(
              'Sie haben bereits einen laufenden Auftrag. Öffnen Sie diesen unter „In Arbeit“ und pausieren oder beenden Sie ihn zuerst.',
            )
          }}
        </p>
        <p v-else-if="running && employee !== running.employee_id" class="wm-inline-hint">
          {{
            t(
              'Diesen Auftrag bearbeitet ein anderer Mitarbeiter. Erst nach einer Pause können Sie ihn übernehmen.',
            )
          }}
        </p>
        <p v-if="running">
          {{ t('In Arbeit bei') }} <strong>{{ employeeName(running.employee_id) }}</strong>
        </p>
        <NcButton
          v-if="['open', 'paused'].includes(current.status)"
          variant="primary"
          :disabled="busy || !employee || active.some((a) => a.employee_id === employee)"
          @click="act('start')"
        >
          {{ current.status === 'paused' ? t('Arbeit fortsetzen') : t('Arbeit starten') }}
        </NcButton>
        <template v-if="current.status === 'active'">
          <strong>{{ t('Was haben Sie erledigt?') }}</strong>
          <div class="wm-checklist">
            <label v-for="(s, n) in current.steps" :key="n" class="wm-check"><input v-model="selectedSteps" type="checkbox" :value="s" />{{ s }}</label>
          </div>
          <div class="wm-chips">
            <NcButton
              v-for="s in snippets.filter((x) => !x.archived)"
              :key="s.id"
              @click="note = [note, s.text].filter(Boolean).join('\n')"
            >
              + {{ s.title }}
            </NcButton>
          </div>
          <label>{{ t('Erledigte Arbeiten / Notizen')
            }}<textarea v-model="note" rows="3" maxlength="10000"></textarea></label><label>{{ t('Pausengrund') }}<input v-model="reason" list="wm-pause" /><datalist
              id="wm-pause"
            >
              <option>Pause</option>
              <option>Warten auf Teile</option>
              <option>Rückfrage</option>
              <option>Feierabend</option>
            </datalist></label>
          <p class="wm-muted">
            {{
              t(
                'Pausieren stoppt die Zeit vorübergehend. Fertig melden stoppt die Zeit und gibt den Auftrag an den Meister zur Prüfung.',
              )
            }}
          </p>
          <div class="wm-actions">
            <NcButton :disabled="busy || employee !== running?.employee_id" @click="act('pause')">
              {{ t('Pausieren') }}
</NcButton><NcButton
              :disabled="busy || employee !== running?.employee_id"
              @click="partForm = !partForm"
            >
              {{ t('Teil anfordern') }}
</NcButton><NcButton
              variant="primary"
              :disabled="busy || employee !== running?.employee_id"
              @click="act('finish')"
            >
              {{ t('Fertig melden') }}
            </NcButton>
          </div>
        </template>
      </fieldset>
      <fieldset v-if="partForm" class="wm-no-print">
        <legend>{{ t('Teileanforderung') }}</legend>
        <p class="wm-muted">
          {{
            t(
              'Der Meister erhält Ihre Anforderung mit Fahrzeug und Auftrag. Wenn Sie ohne das Teil nicht weiterarbeiten können, wählen Sie „Anfordern und pausieren“.',
            )
          }}
        </p>
        <label>{{ t('Teil') }}<input v-model="partTitle" maxlength="255" /></label>
        <div class="wm-two">
          <label>{{ t('Menge') }}<input v-model.number="quantity" type="number" min="1" /></label><label>{{ t('Teilenummer') }}<input v-model="partNumber" maxlength="255" /></label>
        </div>
        <label>{{ t('Dringlichkeit')
          }}<select v-model="partPriority">
            <option value="normal">{{ t('Normal') }}</option>
            <option value="urgent">{{ t('Dringend') }}</option>
          </select></label><label>{{ t('Hinweise') }}<textarea v-model="partNote"></textarea></label><label>{{ t('Foto')
          }}<input type="file" accept="image/jpeg,image/png,image/webp" @change="setPartFile" /></label>
        <div class="wm-actions">
          <NcButton :disabled="busy || !partTitle" @click="requestPart(false)">
            {{ t('Anfordern') }}
</NcButton><NcButton variant="primary" :disabled="busy || !partTitle" @click="requestPart(true)">
            {{ t('Anfordern und pausieren') }}
          </NcButton>
        </div>
      </fieldset>
      <fieldset v-if="master && current.status === 'review'" class="wm-no-print">
        <legend>{{ t('Meisterabnahme') }}</legend>
        <label
          v-for="d in defects.filter(
            (d) => !['resolved', 'dismissed', 'duplicate'].includes(d.status),
          )"
          :key="d.id"
          class="wm-check"
          ><input v-model="resolved" type="checkbox" :value="d.id" />{{ t('Behoben') }}:
          {{ d.description }}</label>
        <p v-if="parts.some((p) => ['requested', 'ordered'].includes(p.status))">
          {{ t('Vor Abnahme offene Teileanforderungen erledigen oder verwerfen.') }}
        </p>
        <NcButton
          variant="primary"
          :disabled="busy || parts.some((p) => ['requested', 'ordered'].includes(p.status))"
          @click="act('approve')"
        >
          {{ t('Abnehmen und abschließen') }}
        </NcButton>
      </fieldset>
      <div v-if="master" class="wm-no-print">
        <NcButton
          v-if="current.status === 'draft'"
          variant="primary"
          :disabled="busy"
          @click="act('release')"
        >
          {{ t('Freigeben') }}
</NcButton><template
          v-if="['draft', 'open', 'paused', 'review', 'completed'].includes(current.status)"
        >
          <label>{{ t('Begründung für Nacharbeit oder Stornierung')
            }}<input v-model="changeReason" maxlength="1000" /></label>
          <div class="wm-actions">
            <NcButton
              v-if="['review', 'completed'].includes(current.status)"
              :disabled="busy || !changeReason"
              @click="act('rework')"
            >
              {{ t('Zur Nacharbeit') }}
</NcButton><NcButton
              v-if="current.status !== 'completed'"
              :disabled="busy || !changeReason"
              @click="act('cancel')"
            >
              {{ t('Stornieren') }}
            </NcButton>
          </div>
        </template>
      </div>
      <section>
        <h3>{{ t('Arbeitsprotokoll') }}</h3>
        <p>
          <strong>{{ t('Istzeit') }}: {{ duration(elapsed) }}</strong><span v-if="current.minutes">
            · {{ t('Abweichung') }}: {{ Math.round(elapsed / 60 - current.minutes) }} min</span>
        </p>
        <article v-for="i in intervals" :key="i.id" class="wm-log">
          <strong>{{ employeeName(i.employee_id) }}</strong> · {{ stamp(i.started) }} —
          {{ i.ended ? stamp(i.ended) : t('läuft') }}
          <p>
            {{ duration((i.ended ?? Date.now() / 1000) - i.started) }}
            <span v-if="!i.ended && Date.now() / 1000 - i.started > 36000" class="wm-error">{{
              t('Lange Buchung – bitte prüfen')
            }}</span>
          </p>
          <ul>
            <li v-for="(s, n) in i.steps" :key="n">{{ s }}</li>
          </ul>
          <p class="wm-preserve">{{ i.note }}</p>
          <NcButton v-if="master && i.ended" class="wm-no-print" @click="correct(i)">
            {{ t('Zeit korrigieren') }}
          </NcButton>
        </article>
        <p v-if="!intervals.length" class="wm-muted">{{ t('Noch keine Arbeitszeit erfasst.') }}</p>
      </section>
      <fieldset v-if="correcting" class="wm-no-print">
        <legend>{{ t('Zeitkorrektur mit Protokoll') }}</legend>
        <div class="wm-two">
          <label>{{ t('Start') }}<input v-model="correctionStart" type="datetime-local" /></label><label>{{ t('Ende') }}<input v-model="correctionEnd" type="datetime-local" /></label>
        </div>
        <label>{{ t('Begründung') }}<input v-model="correctionReason" /></label><NcButton :disabled="busy || !correctionReason" @click="saveCorrection">
          {{ t('Korrektur speichern') }}
        </NcButton>
      </fieldset>
      <section>
        <h3>{{ t('Teile') }}</h3>
        <article v-for="p in parts" :key="p.id" class="wm-log">
          <strong>{{ p.quantity }} × {{ p.title }}</strong> · {{ p.part_number }}
          <span class="wm-badge">{{ statusText(p.status) }}</span>
          <p>{{ p.note }}</p>
          <div v-if="master" class="wm-actions wm-no-print">
            <NcButton
              v-if="p.status === 'requested'"
              :disabled="busy"
              @click="partStatus(p, 'ordered')"
            >
              {{ t('Bestellt') }}
</NcButton><NcButton
              v-if="p.status === 'ordered'"
              :disabled="busy"
              @click="partStatus(p, 'received')"
            >
              {{ t('Eingetroffen') }}
</NcButton><NcButton
              v-if="['requested', 'ordered'].includes(p.status)"
              :disabled="busy"
              @click="partStatus(p, 'cancelled')"
            >
              {{ t('Verwerfen') }}
            </NcButton>
          </div>
        </article>
        <p v-if="!parts.length" class="wm-muted">{{ t('Keine Teileanforderungen.') }}</p>
      </section>
      <section>
        <h3>{{ t('Anhänge') }}</h3>
        <div class="wm-chips">
          <NcButton
            v-for="a in attachments.filter((a) => a.ready !== false)"
            :key="a.id"
            @click="download(a)"
          >
            {{ a.name }}
          </NcButton>
        </div>
        <label class="wm-no-print">{{ t('Foto oder PDF hinzufügen (max. 8 MB)')
          }}<input
            :disabled="busy"
            type="file"
            accept="image/jpeg,image/png,image/webp,application/pdf"
            @change="addFile"
        /></label>
      </section>
      <footer class="wm-actions wm-no-print">
        <NcButton :disabled="busy" @click="reload">{{ t('Aktualisieren') }}</NcButton><NcButton v-if="master" @click="printReport()">{{ t('Auftragsbericht drucken') }}</NcButton><NcButton :disabled="busy" @click="emit('close')">{{ t('Schließen') }}</NcButton>
      </footer>
    </div>
  </Modal>
</template>
