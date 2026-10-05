<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import Modal from './Modal.vue'
import { list, all, command, post, message } from '../api'
import { t, vehicleLabel, iso } from '../format'
import type { Vehicle, Template, Snippet, Order } from '../types'
const props = defineProps<{ vehicle?: Vehicle; defectId?: string; initialDescription?: string }>()
const emit = defineEmits<{ close: []; created: [Order] }>()
const vehicles = ref<Vehicle[]>(props.vehicle ? [props.vehicle] : []),
  templates = ref<Template[]>([]),
  snippets = ref<Snippet[]>([])
const vehicle = ref(props.vehicle?.id ?? ''),
  title = ref(''),
  description = ref(props.initialDescription ?? ''),
  steps = ref(''),
  minutes = ref<number | null>(null),
  start = ref(''),
  end = ref(''),
  priority = ref('normal'),
  query = ref('')
const details = ref(false)
const chosenTemplate = ref('')
const busy = ref(false),
  error = ref(''),
  saved = ref(false)
const quick = computed(() =>
  templates.value
    .filter((v) => !v.archived)
    .sort((a, b) => Number(b.favorite) - Number(a.favorite) || a.position - b.position),
)
const blocks = computed(() =>
  snippets.value
    .filter((v) => !v.archived)
    .sort((a, b) => Number(b.favorite) - Number(a.favorite) || a.position - b.position),
)
let sequence = 0
async function search() {
  const n = ++sequence
  try {
    const p = await list('vehicles', { q: query.value })
    if (n === sequence) {
      vehicles.value = p.items.filter((v) => !v.archived)
      if (props.vehicle && !vehicles.value.some((v) => v.id === props.vehicle?.id))
        vehicles.value.unshift(props.vehicle)
    }
  } catch (e) {
    error.value = message(e)
  }
}
watch(query, search)
onMounted(async () => {
  try {
    ;[templates.value, snippets.value] = await Promise.all([all('templates'), all('snippets')])
    await search()
    try {
      const recent: unknown = JSON.parse(localStorage.getItem('wm_recent') ?? '[]')
      if (Array.isArray(recent))
        vehicles.value.sort(
          (a, b) => (recent.includes(b.id) ? 1 : 0) - (recent.includes(a.id) ? 1 : 0),
        )
    } catch {
      /* Optional local preference; server data remains authoritative. */
    }
  } catch (e) {
    error.value = message(e)
  }
})
function choose(v: Template) {
  chosenTemplate.value = v.id
  title.value = v.title
  description.value = [props.initialDescription, v.description].filter(Boolean).join('\n')
  steps.value = v.steps.join('\n')
  minutes.value = v.minutes
}
function append(v: Snippet) {
  steps.value = [steps.value, v.text].filter(Boolean).join('\n')
}
function data() {
  return {
    vehicle_id: vehicle.value,
    title: title.value,
    description: description.value,
    steps: steps.value
      .split('\n')
      .map((x) => x.trim())
      .filter(Boolean),
    minutes: minutes.value || null,
    planned_start: iso(start.value),
    due_at: iso(end.value),
    priority: priority.value,
    ...(props.defectId ? { defect_id: props.defectId } : {}),
  }
}
async function save(status: 'draft' | 'open') {
  if (busy.value) return
  error.value = ''
  if (!vehicle.value || !title.value.trim()) {
    error.value = t('Bitte Fahrzeug und Titel angeben.')
    return
  }
  if (start.value && end.value && end.value < start.value) {
    error.value = t('Fertigstellung darf nicht vor dem Beginn liegen.')
    return
  }
  busy.value = true
  try {
    const order = await command<Order>('/api/v1/orders', { ...data(), status })
    try {
      const old: unknown = JSON.parse(localStorage.getItem('wm_recent') ?? '[]')
      localStorage.setItem(
        'wm_recent',
        JSON.stringify(
          [
            vehicle.value,
            ...(Array.isArray(old) ? old : []).filter((x) => x !== vehicle.value),
          ].slice(0, 6),
        ),
      )
    } catch {
      /* Selection storage is optional. */
    }
    emit('created', order)
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function saveTemplate() {
  busy.value = true
  try {
    await post('/api/v1/records/templates', {
      data: {
        title: title.value,
        description: description.value,
        steps: data().steps,
        minutes: minutes.value,
        favorite: true,
        position: 0,
      },
    })
    saved.value = true
    templates.value = await all('templates')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <Modal :dirty="!!(title || steps || start || end || minutes || description !== (props.initialDescription ?? '') || vehicle !== (props.vehicle?.id ?? ''))" :title="t('Neuer Auftrag')" @close="!busy && emit('close')">
    <form class="wm-form" @submit.prevent="save('open')">
      <div class="wm-guide">
        <strong>{{ t('In drei Schritten zum Auftrag') }}</strong>
        <p>{{ t('1. Fahrzeug wählen → 2. Arbeit auswählen → 3. Freigeben') }}</p>
        <p>{{ t('Nur Fahrzeug und Auftragstitel sind Pflicht. Alles Weitere ist freiwillig.') }}</p>
      </div>
      <label v-if="!props.vehicle">{{ t('Fahrzeug suchen')
        }}<input
          v-model="query"
          type="search"
          :placeholder="t('Kennzeichen, FIN, Nummer oder Kunde')"
      /></label>
      <label>{{ t('1. Fahrzeug (Pflichtfeld)')
        }}<select v-model="vehicle" required>
          <option value="">{{ t('Bitte auswählen') }}</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option>
        </select></label>
      <fieldset>
        <legend>{{ t('2. Was soll gemacht werden?') }}</legend>
        <p class="wm-muted">
          {{
            t(
              'Eine Vorlage anklicken oder unten einen eigenen Titel eingeben. Eine andere Vorlage ersetzt Titel, Hinweise, Schritte und Zeitvorgabe.',
            )
          }}
        </p>
        <div class="wm-chips">
          <NcButton
            v-for="v in quick"
            :key="v.id"
            :aria-pressed="chosenTemplate === v.id"
            @click="choose(v)"
          >
            {{ v.favorite ? '★ ' : '' }}{{ v.title
            }}<span v-if="v.minutes"> · {{ v.minutes }} min</span>
          </NcButton>
        </div>
        <p v-if="!quick.length" class="wm-muted">
          {{
            t(
              'Vorlagen können unter Vorlagen angelegt werden. Freie Eingabe ist jederzeit möglich.',
            )
          }}
        </p>
      </fieldset>
      <label>{{ t('Auftragstitel (Pflichtfeld)')
        }}<input
          v-model="title"
          required
          maxlength="255"
          :placeholder="t('Was soll gemacht werden?')"
      /></label>
      <div class="wm-two">
        <label>{{ t('Geplante Arbeitsdauer (Minuten, optional)')
          }}<input v-model.number="minutes" type="number" min="1" max="100000" /></label><label>{{ t('Priorität')
          }}<select v-model="priority">
            <option value="low">{{ t('Niedrig') }}</option>
            <option value="normal">{{ t('Normal') }}</option>
            <option value="urgent">{{ t('Dringend') }}</option>
          </select></label>
      </div>
      <div class="wm-chips">
        <NcButton
          v-for="n in [15, 30, 45, 60, 90, 120]"
          :key="n"
          :aria-pressed="minutes === n"
          @click="minutes = n"
        >
          {{ n }} min
</NcButton><NcButton @click="minutes = null">{{ t('Ohne Vorgabe') }}</NcButton>
      </div>
      <details
        class="wm-help"
        :open="details"
        @toggle="details = ($event.target as HTMLDetailsElement).open"
      >
        <summary>{{ t('Hinweise, Arbeitsschritte und Termine ergänzen (optional)') }}</summary>
        <div class="wm-form">
          <label>{{ t('Beschreibung / Hinweise')
            }}<textarea v-model="description" rows="3" maxlength="10000"></textarea>
          </label>
          <fieldset>
            <legend>{{ t('Textbausteine hinzufügen') }}</legend>
            <div class="wm-chips">
              <NcButton v-for="b in blocks" :key="b.id" @click="append(b)">
+ {{ b.title }}
</NcButton>
            </div>
          </fieldset>
          <label>{{ t('Arbeitsschritte – eine Zeile je Schritt')
            }}<textarea v-model="steps" rows="4"></textarea>
          </label>
          <div class="wm-two">
            <label>{{ t('Geplanter Beginn') }}<input v-model="start" type="datetime-local" /></label><label>{{ t('Fertig bis') }}<input v-model="end" type="datetime-local" :min="start" /></label>
          </div>
          <p class="wm-muted">
            {{ t('Termine in der Zeitzone dieses Geräts. Sie starten keine Arbeitszeit.') }}
          </p>
        </div>
      </details>
      <div class="wm-guide">
        <strong>{{ t('3. Auftrag freigeben') }}</strong>
        <p>
          {{
            t(
              '„Freigeben“ zeigt den Auftrag sofort am Werkstatt-Tablet. „Entwurf speichern“ behält ihn zunächst nur beim Meister.',
            )
          }}
        </p>
      </div>
      <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
      <p v-if="saved" role="status">{{ t('Vorlage gespeichert.') }}</p>
      <footer class="wm-actions wm-sticky-actions">
        <NcButton
          :disabled="busy || !title.trim()"
          :title="
            t(
              'Speichert die ausgefüllten Angaben für spätere Aufträge. Erst Freigeben erstellt den offenen Auftrag.',
            )
          "
          @click="saveTemplate"
        >
          {{ t('Als Vorlage speichern') }}
</NcButton><NcButton :disabled="busy" @click="save('draft')">{{ t('Entwurf speichern') }}</NcButton><NcButton variant="primary" :disabled="busy" @click="save('open')">
          {{ busy ? t('Wird gespeichert …') : t('Freigeben') }}
        </NcButton>
      </footer>
    </form>
  </Modal>
</template>
