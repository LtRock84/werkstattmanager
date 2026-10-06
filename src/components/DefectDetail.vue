<script setup lang="ts">
import { ref, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import Modal from './Modal.vue'
import { all, post, url, message } from '../api'
import { t, vehicleLabel, stamp, orderNumber } from '../format'
import type { Defect, Vehicle, Order, Attachment } from '../types'
const props = defineProps<{ closeLabel?: string; defect: Defect; vehicle?: Vehicle }>()
const emit = defineEmits<{ close: []; saved: []; create: [] }>()
const d = ref({ ...props.defect }),
  orders = ref<Order[]>([]),
  others = ref<Defect[]>([]),
  photos = ref<Attachment[]>([]),
  busy = ref(false),
  error = ref('')
onMounted(async () => {
  try {
    orders.value = await all('orders', { vehicle_id: d.value.vehicle_id })
    others.value = await all('defects', { vehicle_id: d.value.vehicle_id })
    photos.value = (await all('attachments', { vehicle_id: d.value.vehicle_id })).filter(
      (a) => a.defect_id === d.value.id && a.ready,
    )
  } catch (e) {
    error.value = message(e)
  }
})
async function save() {
  busy.value = true
  try {
    if (d.value.status !== 'open') d.value.published = false
    await post('/api/v1/actions/defect-update', { data: d.value })
    emit('saved')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function confirmKm() {
  busy.value = true
  try {
    await post('/api/v1/actions/mileage', {
      data: {
        vehicle_id: d.value.vehicle_id,
        value: d.value.mileage,
        date: new Date().toLocaleDateString('sv-SE'),
        defect_id: d.value.id,
      },
    })
    const updated = (await all('defects', { vehicle_id: d.value.vehicle_id })).find(
      (x) => x.id === d.value.id,
    )
    if (updated) {
      d.value.version = updated.version
      d.value.mileage_confirmed = true
    }
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
    const link = document.createElement('a')
    link.href = href
    link.download = a.name
    link.click()
    setTimeout(() => URL.revokeObjectURL(href), 1000)
  } catch (e) {
    error.value = message(e)
  }
}
</script>
<template>
  <Modal :close-label="closeLabel" :title="t('Mängelmeldung prüfen')" @close="!busy && emit('close')">
    <div class="wm-form">
      <strong>{{ vehicleLabel(vehicle) }}</strong>
      <p>{{ stamp(d.created_at) }} · {{ d.reporter || t('Ohne Namensangabe') }}</p>
      <p class="wm-preserve">{{ d.description }}</p>
      <p>
        {{ t('Gemeldete Dringlichkeit') }}:
        {{ d.priority === 'urgent' ? t('Dringend') : t('Normal') }}
      </p>
      <div v-if="d.mileage !== null">
        <p>{{ t('Gemeldeter Kilometerstand') }}: {{ d.mileage }}</p>
        <NcButton v-if="!d.mileage_confirmed" :disabled="busy" @click="confirmKm">
          {{ t('Kilometerstand bestätigen') }}
        </NcButton>
        <p v-else>{{ t('Kilometerstand bestätigt') }}</p>
      </div>
      <div class="wm-chips">
        <NcButton v-for="a in photos" :key="a.id" @click="download(a)">{{ a.name }}</NcButton>
      </div>
      <label>{{ t('Prüfergebnis')
        }}<select v-model="d.status">
          <option value="new">{{ t('Ungeprüft') }}</option>
          <option value="open">{{ t('Bestätigt / offen') }}</option>
          <option value="resolved">{{ t('Behoben') }}</option>
          <option value="duplicate">{{ t('Doppelte Meldung') }}</option>
          <option value="dismissed">{{ t('Verworfen') }}</option>
        </select></label>
      <label v-if="d.status === 'duplicate'">{{ t('Bereits bekannter Mangel')
        }}<select v-model="d.duplicate_id">
          <option value="">{{ t('Auswählen') }}</option>
          <option
            v-for="other in others.filter(
              (x) => x.id !== d.id && ['new', 'open'].includes(x.status),
            )"
            :key="other.id"
            :value="other.id"
          >
            {{ other.description }}
          </option>
        </select></label>
      <label>{{ t('Bestehendem Auftrag zuordnen')
        }}<select v-model="d.order_id">
          <option :value="null">{{ t('Ohne Auftrag') }}</option>
          <option
            v-for="o in orders.filter((x) => !['completed', 'cancelled'].includes(x.status))"
            :key="o.id"
            :value="o.id"
          >
            {{ orderNumber(o.id) }} · {{ o.title }}
          </option>
        </select></label>
      <fieldset>
        <legend>{{ t('Was dürfen Fahrer sehen?') }}</legend>
        <p class="wm-muted">
          {{
            t(
              'Nur dieser Kurztext wird nach Ihrer Freigabe am Fahrzeuglink angezeigt. Interne Notizen und Fotos bleiben in der Werkstatt. Zum Veröffentlichen muss der Mangel den Status „Offen“ haben.',
            )
          }}
        </p>
        <label>{{ t('Öffentlicher Kurztext – ohne personenbezogene oder interne Angaben')
          }}<textarea v-model="d.public_text" rows="3" maxlength="2000"></textarea></label><label class="wm-check"><input v-model="d.published" type="checkbox" :disabled="d.status !== 'open'" />{{
            t('Für Fahrer mit Fahrzeuglink sichtbar')
          }}</label>
      </fieldset>
      <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
      <footer class="wm-actions">
        <NcButton
          v-if="!d.order_id && ['new', 'open'].includes(d.status)"
          :disabled="busy"
          @click="emit('create')"
        >
          {{ t('Neuen Auftrag aus Meldung erstellen') }}
</NcButton><NcButton variant="primary" :disabled="busy" @click="save">
          {{ t('Prüfung speichern') }}
        </NcButton>
      </footer>
    </div>
  </Modal>
</template>
