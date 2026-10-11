<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import Modal from './Modal.vue'
import { all, post, message } from '../api'
import { useDirtySnapshot, confirmDiscard } from '../dirty'
import { t, vehicleLabel } from '../format'
import type { Editable, Row, Customer, Vehicle } from '../types'
const props = defineProps<{ closeLabel?: string; kind: Editable; row?: Row; vehicleId?: string; customerId?: string }>()
const emit = defineEmits<{ close: []; saved: [Row] }>()
type FormValue = string | number | boolean | null
const form = ref<Record<string, FormValue>>({
  kind: props.customerId ? 'customer' : 'fleet',
  customer_id: props.customerId ?? '',
  vehicle_class: 'car',
  position: 0,
  favorite: false,
  archived: false,
  deadline_emails: true,
  vehicle_id: props.vehicleId ?? '',
  warning_days: '30, 7',
})
const customers = ref<Customer[]>([]),
  vehicles = ref<Vehicle[]>([]),
  busy = ref(false),
  error = ref('')
const labels: Record<Editable, string> = {
  customers: t('Kunde'),
  vehicles: t('Fahrzeug'),
  employees: t('Mitarbeiter'),
  templates: t('Auftragsvorlage'),
  snippets: t('Textbaustein'),
  deadlines: t('Termin / Frist'),
}
interface Field {
  key: string
  label: string
  type?: string
  required?: boolean
}
const fields = computed<Field[]>(
  () =>
    ({
      customers: [
        { key: 'name', label: t('Name / Firma'), required: true },
        { key: 'contact', label: t('Ansprechpartner') },
        { key: 'email', label: t('E-Mail'), type: 'email' },
        { key: 'phone', label: t('Telefon') },
        { key: 'notes', label: t('Hinweise'), type: 'textarea' },
      ],
      vehicles: [
        { key: 'plate', label: t('Kennzeichen') },
        { key: 'number', label: t('Interne Fahrzeugnummer') },
        { key: 'vin', label: t('Fahrgestellnummer (FIN)') },
        { key: 'make', label: t('Hersteller') },
        { key: 'model', label: t('Modell') },
        { key: 'registration', label: t('Erstzulassung'), type: 'date' },
        { key: 'notes', label: t('Hinweise'), type: 'textarea' },
      ],
      employees: [{ key: 'name', label: t('Name'), required: true }],
      templates: [
        { key: 'title', label: t('Titel'), required: true },
        { key: 'description', label: t('Beschreibung'), type: 'textarea' },
        { key: 'steps', label: t('Arbeitsschritte – eine Zeile je Schritt'), type: 'textarea' },
        { key: 'minutes', label: t('Übliche Arbeitsdauer in Minuten'), type: 'number' },
        { key: 'position', label: t('Reihenfolge (kleine Zahlen zuerst)'), type: 'number' },
      ],
      snippets: [
        { key: 'title', label: t('Kurzbezeichnung'), required: true },
        { key: 'text', label: t('Text'), type: 'textarea', required: true },
        { key: 'position', label: t('Reihenfolge (kleine Zahlen zuerst)'), type: 'number' },
      ],
      deadlines: [
        { key: 'title', label: t('Bezeichnung'), required: true },
        { key: 'due_date', label: t('Fällig am'), type: 'date' },
        { key: 'last_completed_date', label: t('Zuletzt erledigt am'), type: 'date' },
        { key: 'due_km', label: t('Fällig bei Kilometerstand'), type: 'number' },
        { key: 'repeat_months', label: t('Wiederholung in Monaten'), type: 'number' },
        { key: 'repeat_km', label: t('Wiederholung nach Kilometern'), type: 'number' },
        { key: 'warning_days', label: t('Vorwarnung in Tagen, durch Komma getrennt') },
      ],
    })[props.kind],
)
const { dirty, reset } = useDirtySnapshot(() => ({ ...Object.fromEntries(fields.value.map(f => [f.key, form.value[f.key] ?? ''])), kind: form.value.kind, vehicleClass: form.value.vehicle_class, customer: form.value.customer_id, vehicle: form.value.vehicle_id, archived: form.value.archived, favorite: form.value.favorite, deadlineEmails: form.value.deadline_emails }))
function close() { if (!busy.value && confirmDiscard(dirty.value)) emit('close') }
onMounted(async () => {
  if (props.row)
    for (const [k, v] of Object.entries(props.row)) {
      if (Array.isArray(v)) form.value[k] = v.join(k === 'warning_days' ? ', ' : '\n')
      else if (v === null || ['string', 'number', 'boolean'].includes(typeof v))
        form.value[k] = v as FormValue
    }
  if (props.row && props.kind === 'customers') form.value.deadline_emails = (props.row as Customer).deadline_emails === true
  reset()
  try {
    if (props.kind === 'vehicles') customers.value = await all('customers')
    if (props.kind === 'deadlines') vehicles.value = await all('vehicles')
  } catch (e) {
    error.value = message(e)
  }
})
function presetDeadline(months: number) {
  form.value.title = months === 24 ? 'HU / TÜV' : 'Inspektion'
  form.value.repeat_months = months
  form.value.due_date = ''
}
async function save() {
  busy.value = true
  error.value = ''
  try {
    const data: Record<string, unknown> = { ...form.value }
    if (props.kind === 'templates')
      data.steps = String(data.steps ?? '')
        .split('\n')
        .map((x) => x.trim())
        .filter(Boolean)
    if (props.kind === 'deadlines')
      data.warning_days = String(data.warning_days ?? '30,7')
        .split(',')
        .map((x) => Number(x.trim()))
    const saved = await post<Row>(`/api/v1/records/${props.kind}`, { id: props.row?.id ?? null, data })
    reset()
    emit('saved', saved)
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <Modal :dirty="dirty" :locked="busy" :close-label="closeLabel" :title="labels[kind]" @close="!busy && emit('close')">
    <form class="wm-form" @submit.prevent="save">
      <p class="wm-muted">
        {{
          t(
            'Felder mit „Pflichtfeld“ bitte ausfüllen. Mit „Speichern“ übernehmen Sie Ihre Angaben; „Abbrechen“ schließt ohne Speichern.',
          )
        }}
      </p>
      <p v-if="kind === 'deadlines'" class="wm-guide">{{ t('Tragen Sie ein Datum, einen Kilometerstand oder beides ein. Die Frist ist fällig, sobald eine der Vorgaben erreicht ist. Wiederholung leer lassen für einen einmaligen Termin. „30, 7“ warnt 30 und 7 Tage vorher.') }}</p>
      <p v-if="kind === 'vehicles'" class="wm-guide">{{ t('Bitte mindestens Kennzeichen oder interne Fahrzeugnummer angeben. Bei Kundenfahrzeugen zusätzlich den Kunden auswählen. Kunden lassen sich unter „Stammdaten & Vorlagen“ anlegen.') }}</p>
      <template v-if="kind === 'vehicles'">
        <label>{{ t('Fahrzeugart')
          }}<select v-model="form.kind">
            <option value="fleet">{{ t('Eigene Flotte') }}</option>
            <option value="customer">{{ t('Kundenfahrzeug') }}</option>
          </select></label><label v-if="form.kind === 'customer'">{{ t('Kunde (Pflichtfeld)')
          }}<select v-model="form.customer_id" required>
            <option value="">{{ t('Bitte auswählen') }}</option>
            <option v-for="c in customers.filter((x) => !x.archived)" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select></label>
      </template>
      <label v-if="kind === 'deadlines'">{{ t('Fahrzeug (Pflichtfeld)')
        }}<select v-model="form.vehicle_id" required>
          <option value="">{{ t('Bitte auswählen') }}</option>
          <option v-for="v in vehicles.filter((x) => !x.archived)" :key="v.id" :value="v.id">
            {{ vehicleLabel(v) }}
          </option>
        </select></label>
      <div v-if="kind === 'deadlines'" class="wm-actions"><NcButton @click="presetDeadline(24)">{{ t('HU / TÜV – alle 24 Monate') }}</NcButton><NcButton @click="presetDeadline(12)">{{ t('Inspektion – alle 12 Monate') }}</NcButton></div>
      <p v-if="kind === 'deadlines'" class="wm-muted">{{ t('Bei letzter Erledigung und Wiederholung kann „Fällig am“ leer bleiben: Die nächste Fälligkeit wird daraus berechnet. Abweichende Fahrzeugintervalle bitte anpassen. Wiederkehrende Fristen entstehen nach bestätigter Erledigung erneut.') }}</p>
      <label v-if="kind === 'customers'" class="wm-check"><input v-model="form.deadline_emails" type="checkbox" />{{ t('Kunden automatisch per E-Mail an Fahrzeugfristen erinnern') }}</label>
      <p v-if="kind === 'customers'" class="wm-muted">{{ t('Erinnert an HU, Inspektion und weitere hinterlegte Fristen, um einen Termin zu vereinbaren. Eine gültige E-Mail und die gewünschte Kundenbenachrichtigung müssen vorliegen. Abwünsche hier sofort ausschalten; bereits versendete Nachrichten können nicht zurückgerufen werden.') }}</p>
      <label v-if="kind === 'vehicles'">{{ t('Fahrzeugklasse') }}<select v-model="form.vehicle_class"><option value="car">{{ t('PKW') }}</option><option value="van">{{ t('Leichter Transporter') }}</option></select></label>
<label v-for="field in fields" :key="field.key">{{ field.label }}{{ field.required ? ' · ' + t('Pflichtfeld') : ''
        }}<textarea
          v-if="field.type === 'textarea'"
          :value="String(form[field.key] ?? '')"
          rows="4"
          :required="field.required"
          maxlength="10000"
          @input="form[field.key] = ($event.target as HTMLTextAreaElement).value"
        ></textarea><input
          v-else
          v-model="form[field.key]"
          :type="field.type ?? 'text'"
          :required="field.required"
          :min="field.type === 'number' ? 0 : undefined"
          :maxlength="field.type ? undefined : 255"
      /></label>
      <label v-if="['templates', 'snippets'].includes(kind)" class="wm-check"><input v-model="form.favorite" type="checkbox" />{{ t('Als Favorit anzeigen') }}</label>
      <label v-if="row && kind !== 'deadlines'" class="wm-check"><input v-model="form.archived" type="checkbox" />{{
          t('Archivieren – aus der aktiven Auswahl ausblenden')
        }}</label>
      <p v-if="kind === 'vehicles'" class="wm-muted">
        {{ t('Kilometerstände werden in der Fahrzeugakte bestätigt und protokolliert.') }}
      </p>
      <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
      <footer class="wm-actions">
        <NcButton :disabled="busy" @click="close">{{ t('Abbrechen') }}</NcButton><NcButton variant="primary" :disabled="busy" @click="save">{{ t('Speichern') }}</NcButton>
      </footer>
    </form>
  </Modal>
</template>
