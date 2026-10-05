<script setup lang="ts">
import { ref, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import QRCode from 'qrcode'
import Modal from './Modal.vue'
import { all, post, url, upload, message } from '../api'
import { t, vehicleLabel, stamp, date, statusText, orderNumber } from '../format'
import type { Vehicle, Order, Defect, Deadline, Attachment, Audit, Mileage } from '../types'
const props = defineProps<{ vehicle: Vehicle }>()
const emit = defineEmits<{
  close: []
  edit: [Vehicle]
  order: [Order]
  create: []
  defect: [Defect]
  deadline: []
  changed: []
}>()
const v = ref(props.vehicle),
  tab = ref('history'),
  orders = ref<Order[]>([]),
  defects = ref<Defect[]>([]),
  deadlines = ref<Deadline[]>([]),
  attachments = ref<Attachment[]>([]),
  audit = ref<Audit[]>([]),
  mileage = ref<Mileage[]>([])
const km = ref<number | null>(props.vehicle.mileage),
  kmDate = ref(new Date().toLocaleDateString('sv-SE')),
  qr = ref(''),
  link = ref(''),
  busy = ref(false),
  error = ref(''),
  notice = ref('')
async function load() {
  ;[orders.value, defects.value, deadlines.value, attachments.value, audit.value, mileage.value] =
    await Promise.all([
      all('orders', { vehicle_id: v.value.id }),
      all('defects', { vehicle_id: v.value.id }),
      all('deadlines', { vehicle_id: v.value.id }),
      all('attachments', { vehicle_id: v.value.id }),
      all('audit', { vehicle_id: v.value.id }),
      all('mileage', { vehicle_id: v.value.id }),
    ])
}
onMounted(async () => {
  try {
    await load()
  } catch (e) {
    error.value = message(e)
  }
})
async function saveKm() {
  busy.value = true
  try {
    v.value = await post('/api/v1/actions/mileage', {
      data: { vehicle_id: v.value.id, value: km.value, date: kmDate.value },
    })
    await load()
    emit('changed')
    notice.value = t('Kilometerstand bestätigt.')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function createLink(revoke = false) {
  busy.value = true
  try {
    const r = await post<{ token?: string }>('/api/v1/actions/vehicle-link', {
      data: { vehicle_id: v.value.id, revoke },
    })
    qr.value = ''
    link.value = ''
    if (r.token) {
      link.value = location.origin + url('/fahrer') + '#' + r.token
      qr.value = await QRCode.toDataURL(link.value, { width: 360, margin: 2 })
    }
    notice.value = revoke
      ? t('Fahrerlinks widerrufen.')
      : t('Neuer Link erstellt. Bisherige QR-Codes sind jetzt ungültig.')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function complete(d: Deadline) {
  busy.value = true
  try {
    await post('/api/v1/actions/deadline-complete', {
      data: { id: d.id, date: kmDate.value, km: km.value },
    })
    await load()
    emit('changed')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function addFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  busy.value = true
  try {
    await upload('/api/v1/attachments', file, { vehicle_id: v.value.id })
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
    const r = await axios.get(url(`/api/v1/attachments/${a.id}`), { responseType: 'blob' })
    const href = URL.createObjectURL(r.data)
    const anchor = document.createElement('a')
    anchor.href = href
    anchor.download = a.name
    anchor.click()
    setTimeout(() => URL.revokeObjectURL(href), 1000)
  } catch (e) {
    error.value = message(e)
  }
}
const print = () => window.print()
const tabs = [
  ['history', t('Historie')],
  ['orders', t('Aufträge')],
  ['defects', t('Mängel')],
  ['deadlines', t('Termine')],
  ['files', t('Dokumente')],
  ['driver', t('Fahrer-QR')],
]
</script>
<template>
  <Modal :title="vehicleLabel(v)" @close="!busy && emit('close')">
    <div class="wm-form">
      <p class="wm-guide wm-no-print">
        {{
          t(
            'Hier gehört alles zu diesem Fahrzeug zusammen. „Neuer Auftrag“ übernimmt das Fahrzeug automatisch. Über die Schaltflächen unten wechseln Sie zwischen Aufträgen, Mängeln und Terminen.',
          )
        }}
      </p>
      <div class="wm-actions wm-no-print">
        <NcButton variant="primary" :disabled="v.archived" @click="emit('create')">
          {{ t('Neuer Auftrag') }}
</NcButton><NcButton @click="emit('edit', v)">{{ t('Stammdaten bearbeiten') }}</NcButton>
      </div>
      <div class="wm-three">
        <div>
          <small>{{ t('FIN') }}</small><strong>{{ v.vin || '–' }}</strong>
        </div>
        <div>
          <small>{{ t('Erstzulassung') }}</small><strong>{{ date(v.registration) }}</strong>
        </div>
        <div>
          <small>{{ t('Kilometerstand') }}</small><strong>{{ v.mileage ?? '–' }} km</strong><small>{{ date(v.mileage_date) }}</small>
        </div>
      </div>
      <p class="wm-preserve">{{ v.notes }}</p>
      <fieldset class="wm-no-print">
        <legend>{{ t('Kilometerstand bestätigen / Erledigungsdaten') }}</legend>
        <div class="wm-two">
          <label>{{ t('Kilometer') }}<input v-model.number="km" type="number" min="0" /></label><label>{{ t('Datum') }}<input v-model="kmDate" type="date" /></label>
        </div>
        <NcButton :disabled="busy || km === null" @click="saveKm">
          {{ t('Kilometerstand bestätigen') }}
        </NcButton>
      </fieldset>
      <nav class="wm-tabs wm-no-print" :aria-label="t('Fahrzeugakte')">
        <NcButton
          v-for="[key, label] in tabs"
          :key="key"
          :aria-pressed="tab === key"
          @click="tab = key"
        >
          {{ label }}
        </NcButton>
      </nav>
      <section v-if="tab === 'orders'">
        <article v-for="o in orders" :key="o.id" class="wm-log">
          <NcButton @click="emit('order', o)">{{ orderNumber(o.id) }} · {{ o.title }}</NcButton><span class="wm-badge">{{ statusText(o.status) }}</span>
        </article>
        <p v-if="!orders.length">{{ t('Noch keine Aufträge.') }}</p>
      </section>
      <section v-if="tab === 'defects'">
        <article v-for="d in defects" :key="d.id" class="wm-log">
          <p>{{ d.description }}</p>
          <span class="wm-badge">{{ statusText(d.status) }}</span><NcButton @click="emit('defect', d)">{{ t('Prüfen / bearbeiten') }}</NcButton>
        </article>
        <p v-if="!defects.length">{{ t('Keine Mängelmeldungen.') }}</p>
      </section>
      <section v-if="tab === 'deadlines'">
        <NcButton @click="emit('deadline')">{{ t('Termin / Frist hinzufügen') }}</NcButton>
        <article v-for="d in deadlines" :key="d.id" class="wm-log">
          <strong>{{ d.title }}</strong>
          <p>
            {{ date(d.due_date) }} <span v-if="d.due_km !== null"> · {{ d.due_km }} km</span>
          </p>
          <span class="wm-badge">{{ statusText(d.status) }}</span><NcButton v-if="d.status === 'open'" :disabled="busy" @click="complete(d)">
            {{ t('Mit obigen Erledigungsdaten abschließen') }}
          </NcButton>
        </article>
      </section>
      <section v-if="tab === 'files'">
        <label>{{ t('Foto / PDF hinzufügen (max. 8 MB)')
          }}<input
            type="file"
            :disabled="busy"
            accept="image/jpeg,image/png,image/webp,application/pdf"
            @change="addFile"
        /></label>
        <div class="wm-chips">
          <NcButton
            v-for="a in attachments.filter((a) => a.ready)"
            :key="a.id"
            @click="download(a)"
          >
            {{ a.name }}
          </NcButton>
        </div>
      </section>
      <section v-if="tab === 'driver'" class="wm-qr">
        <template v-if="v.kind === 'fleet' && !v.archived">
          <p class="wm-no-print">
            {{
              t(
                'Ein neuer QR-Code ersetzt alle bisherigen Fahrerlinks dieses Fahrzeugs. Der Link erlaubt Meldungen und zeigt freigegebene offene Mängel.',
              )
            }}
          </p>
          <div class="wm-actions wm-no-print">
            <NcButton :disabled="busy" variant="primary" @click="createLink()">
              {{ t('Neuen Fahrzeug-QR-Code erzeugen') }}
</NcButton><NcButton :disabled="busy" @click="createLink(true)">
              {{ t('Alle Fahrerlinks widerrufen') }}
            </NcButton>
          </div>
          <template v-if="qr">
            <h3>{{ t('Mangel melden') }} · {{ v.plate || v.number }}</h3>
            <img :src="qr" :alt="t('QR-Code zur Mängelmeldung')" />
            <p>{{ t('Scannen, Mangel beschreiben, absenden.') }}</p>
            <label class="wm-no-print">{{ t('Fahrzeuglink – sicher weitergeben')
              }}<input
                :value="link"
                readonly
                @focus="($event.target as HTMLInputElement).select()"
/></label><NcButton class="wm-no-print" @click="print">
              {{ t('QR-Code drucken') }}
            </NcButton>
          </template>
        </template>
        <p v-else>{{ t('Fahrerlinks sind für aktive Flottenfahrzeuge verfügbar.') }}</p>
      </section>
      <section v-if="tab === 'history'">
        <h3>{{ t('Kilometerhistorie') }}</h3>
        <p v-for="m in mileage" :key="m.id">{{ date(m.date) }} · {{ m.value }} km</p>
        <h3>{{ t('Änderungsprotokoll') }}</h3>
        <article v-for="a in audit" :key="a.id" class="wm-log">
          <strong>{{ a.action }}</strong> · {{ stamp(a.created_at) }}
          <p>{{ a.actor }}</p>
          <pre v-if="Object.keys(a.details).length">{{ JSON.stringify(a.details, null, 2) }}</pre>
        </article>
      </section>
      <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
      <p v-if="notice" role="status">{{ notice }}</p>
    </div>
  </Modal>
</template>
