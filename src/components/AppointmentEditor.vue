<script setup lang="ts">
import { computed, onMounted, ref, watch, nextTick } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import Modal from './Modal.vue'
import EntityEditor from './EntityEditor.vue'
import AppointmentCalendar from './AppointmentCalendar.vue'
import ServicePicker from './ServicePicker.vue'
import ReceptionChecklist from './ReceptionChecklist.vue'
import ExistingWorkNotice from './ExistingWorkNotice.vue'
import { all, get, command, message } from '../api'
import { useDirtySnapshot } from '../dirty'
import { t, vehicleLabel } from '../format'
import { lineMinutes, berlinInput, berlinIso } from '../services'
import { emptyChecklist, followUpWork, freePlanningDays, shiftBookDay } from '../planning'
import type { Appointment, Vehicle, Customer, ServiceLine, Allocation, Row, BookView, CapacityDay } from '../types'
const props = defineProps<{ appointment?: Appointment; copyFrom?: Appointment; day: string; reception?: boolean }>()
const pendingWork = ref(false)
const emit = defineEmits<{ close: []; saved: [appointment: Appointment] }>()
const original = props.appointment
const copied = props.copyFrom ? followUpWork(props.copyFrom) : undefined
const vehicle = ref(original?.vehicle_id ?? copied?.vehicle_id ?? ''), title = ref(original?.title ?? copied?.title ?? ''), email = ref(original?.customer_email ?? '')
const arrival = ref(original ? berlinInput(original.arrival) : props.day + 'T08:00'), start = ref(berlinInput(original?.work_start)), pickup = ref(berlinInput(original?.pickup))
const lines = ref<ServiceLine[]>(JSON.parse(JSON.stringify(original?.service_lines ?? copied?.service_lines ?? []))), minutes = ref<number | null>(original?.minutes ?? copied?.minutes ?? null)
const checklist = ref({ ...emptyChecklist(), ...original?.checklist })
const suggested = ref<CapacityDay[] | null>(null), finding = ref(false)
let suggestionRevision = 0
async function findFreeDays() {
  const day = arrival.value.slice(0, 10), n = ++suggestionRevision
  finding.value = true; error.value = ''
  try { const result = await get<BookView>(`/api/v1/appointments?from=${day}&to=${shiftBookDay(day, 'day', 13)}`); if (n === suggestionRevision) suggested.value = freePlanningDays(result.days, minutes.value) }
  catch (e) { if (n === suggestionRevision) error.value = message(e) }
  finally { finding.value = false }
}
function chooseDay(day: string) { if (day === arrival.value.slice(0, 10)) return; arrival.value = day + 'T' + (arrival.value.slice(11) || '08:00'); start.value = ''; pickup.value = ''; suggested.value = null }
const allocations = ref<Allocation[]>(JSON.parse(JSON.stringify(original?.allocations ?? []))), reason = ref(original?.override_reason ?? ''), note = ref(original?.note ?? ''), reminder = ref(original?.reminder ?? false), notifyReady = ref(original?.notify_ready ?? false), stage = ref(original?.stage ?? 'planned')
watch([arrival, minutes, allocations], () => { ++suggestionRevision; suggested.value = null }, { deep: true })
const vehicles = ref<Vehicle[]>([]), customers = ref<Customer[]>([]), error = ref(''), busy = ref(false), inlineEditor = ref<'customers' | 'vehicles' | null>(null)
const customerId = ref(''), cancelOpen = ref(false), cancelPrompt = ref<HTMLDivElement | null>(null)
function askCancel() { cancelOpen.value = true; void nextTick(() => cancelPrompt.value?.focus()) }
const { dirty } = useDirtySnapshot(() => ({ vehicle: vehicle.value, title: title.value, email: email.value, arrival: arrival.value, start: start.value, pickup: pickup.value, lines: lines.value, minutes: minutes.value, allocations: allocations.value, reason: reason.value, note: note.value, reminder: reminder.value, notify_ready: notifyReady.value, stage: stage.value, checklist: checklist.value }))
async function cancel() {
  if (!original || busy.value) return
  busy.value = true; error.value = ''
  try { const saved = await command<Appointment>('/api/v1/appointments', { ...original, status: 'cancelled' }); emit('saved', saved) } catch (e) { error.value = message(e) } finally { busy.value = false }
}
const selectedVehicle = computed(() => vehicles.value.find(v => v.id === vehicle.value))
let automaticTitle = lines.value.map(l => l.title).join(' / ').slice(0, 255)
watch(lines, v => { minutes.value = v.length ? lineMinutes(v) : null; const next = v.map(l => l.title).join(' / ').slice(0, 255); if (!title.value.trim() || title.value === automaticTitle) title.value = next; automaticTitle = next }, { deep: true })
watch(vehicle, () => { const c = customers.value.find(c => c.id === selectedVehicle.value?.customer_id); email.value = c?.email ?? '' })
onMounted(async () => { try { [vehicles.value, customers.value] = await Promise.all([all('vehicles'), all('customers')]); if (copied) email.value = customers.value.find(c => c.id === selectedVehicle.value?.customer_id)?.email ?? '' } catch (e) { error.value = message(e) } })
async function inlineSaved(row: Row) {
  inlineEditor.value = null
  if ('plate' in row) { vehicles.value.unshift(row); vehicle.value = row.id }
  else if ('email' in row) { customers.value.unshift(row); customerId.value = row.id; email.value = row.email; inlineEditor.value = 'vehicles' }
}
async function save(status: Appointment['status']) {
  if (busy.value) return
  error.value = ''; busy.value = true
  try {
    const saved = await command<Appointment>('/api/v1/appointments', { id: original?.id, version: original?.version, vehicle_id: vehicle.value, title: title.value, customer_email: email.value, arrival: berlinIso(arrival.value), work_start: berlinIso(start.value), pickup: berlinIso(pickup.value), service_lines: lines.value, minutes: minutes.value, allocations: allocations.value, override_reason: reason.value, note: note.value, reminder: reminder.value, notify_ready: notifyReady.value, stage: stage.value, checklist: checklist.value, status })
    emit('saved', saved)
  } catch (e) { error.value = message(e) } finally { busy.value = false }
}
</script>
<template>
  <Modal :title="original ? t('Kundentermin bearbeiten') : t('Kundentermin anlegen')" :dirty="dirty || pendingWork" :locked="busy" @close="!busy && emit('close')">
    <div v-if="cancelOpen" ref="cancelPrompt" tabindex="-1" class="wm-guide" role="group" :aria-label="t('Terminabsage bestätigen')">
<h3>{{ t('Diesen gespeicherten Termin absagen?') }}</h3><p>{{ original?.title }} · {{ original?.customer_email || t('Keine Kunden-E-Mail hinterlegt') }}</p><p>{{ t('Der Kalendertermin wird abgesagt. Bei hinterlegter E-Mail erhält der Kunde eine Absage. Ungespeicherte Änderungen werden verworfen.') }}</p><NcButton :disabled="busy" @click="cancelOpen = false">{{ t('Weiter bearbeiten') }}</NcButton><NcButton :disabled="busy" variant="error" @click="cancel">{{ t('Absage bestätigen') }}</NcButton>
<p v-if="error" role="alert">{{ error }}</p>
</div>
    <form v-show="!cancelOpen" class="wm-form" @submit.prevent="save('confirmed')">
      <p class="wm-guide">{{ t('Fahrzeug wählen → Arbeiten auswählen → Ankunft und Abholung eintragen → Termin bestätigen.') }}</p>
      <p v-if="copied" class="wm-guide">{{ t('Folgetermin vorbereitet. Fahrzeug und Arbeiten sind übernommen; bitte neuen Tag, Zeiten und aktuelle Kundenadresse prüfen. Bisher wurde nichts gespeichert oder versendet.') }}</p>
      <p v-if="original?.order_id" class="wm-guide">{{ t('Es gibt bereits einen Arbeitsauftrag. Änderungen hier ändern diesen Auftrag nicht. Bitte Absprachen mit der Werkstatt prüfen.') }}</p>
      <label>{{ t('Fahrzeug (Pflichtfeld)') }}<select v-model="vehicle" required :disabled="!!original?.order_id"><option value="">{{ t('Bitte auswählen') }}</option><option v-for="v in vehicles.filter(v => !v.archived)" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option></select></label>
      <div v-if="!reception" class="wm-actions"><NcButton @click="inlineEditor = 'customers'">+ {{ t('Neuer Kunde') }}</NcButton><NcButton @click="inlineEditor = 'vehicles'">+ {{ t('Neues Fahrzeug') }}</NcButton></div>
      <ExistingWorkNotice v-if="!reception" :vehicle-id="vehicle" :exclude-order-id="original?.order_id" />
      <ServicePicker v-model="lines" :hide-prices="reception" :vehicle-class="selectedVehicle?.vehicle_class ?? 'car'" @draft-change="pendingWork = $event" />
      <label>{{ t('Terminbezeichnung (Pflichtfeld)') }}<input v-model="title" required maxlength="255" /></label>
      <label>{{ t('Gesamte Arbeitsdauer in Minuten') }}<input v-model.number="minutes" type="number" min="1" max="100000" :readonly="!!lines.length" /></label>
      <AppointmentCalendar :day="arrival.slice(0, 10)" :minutes="minutes" :appointment-id="original?.id" :multiday="!!allocations.length" @choose="chooseDay" />
      <div class="wm-two"><label>{{ t('Ankunft / Fahrzeugannahme') }}<input v-model="arrival" type="datetime-local" required /></label><label>{{ t('Zugesagte Abholung (optional)') }}<input v-model="pickup" type="datetime-local" :min="arrival" /></label></div>
      <label>{{ t('Arbeitsbeginn (optional)') }}<input v-model="start" type="datetime-local" :min="arrival" /></label>
      <p>{{ t('Alle Uhrzeiten in Europe/Berlin. Ein Termin startet keine Zeiterfassung.') }}</p>
      <div v-if="!original && !allocations.length" class="wm-guide">
        <NcButton :disabled="finding || !minutes || !arrival" @click="findFreeDays">{{ finding ? t('Freie Tage werden gesucht …') : t('Passende freie Tage finden') }}</NcButton>
        <p>{{ t('Prüft die nächsten 14 Tage ab Ankunft auf Arbeitszeit und Fahrzeuglimit. Keine Reservierung und keine zugesicherte Uhrzeit. Übernahme ändert den Ankunftstag; Arbeitsbeginn und Abholung bitte neu festlegen.') }}</p>
        <div v-if="suggested" role="status"><p v-if="!suggested.length">{{ t('Kein passender Tag im Suchzeitraum. Anderes Datum oder Mehrtagesplanung wählen.') }}</p><div class="wm-actions"><NcButton v-for="d in suggested" :key="d.date" @click="chooseDay(d.date)">{{ new Date(d.date + 'T12:00:00Z').toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) }} · {{ d.free }} {{ t('Minuten frei') }}</NcButton></div></div>
      </div>
      <details class="wm-help">
<summary>{{ t('Arbeit auf mehrere Tage verteilen') }}</summary><p>{{ t('Ohne Aufteilung zählt die gesamte Dauer am Tag des Arbeitsbeginns, sonst am Ankunftstag. Die Summe der Tage muss der Gesamtdauer entsprechen.') }}</p>
        <div v-for="(a, i) in allocations" :key="i" class="wm-two"><label>{{ t('Tag') }}<input v-model="a.date" type="date" /></label><label>{{ t('Arbeitsminuten') }}<input v-model.number="a.minutes" type="number" min="1" /></label><NcButton @click="allocations.splice(i, 1)">{{ t('Tag entfernen') }}</NcButton></div>
        <NcButton @click="allocations.push({ date: arrival.slice(0, 10), minutes: minutes ?? 60 })">+ {{ t('Planungstag hinzufügen') }}</NcButton>
      </details>
      <label>{{ t('E-Mail des Kunden (optional)') }}<input v-model="email" type="email" maxlength="255" /></label><p>{{ t('Mit „Termin bestätigen“ wird eine Bestätigung mit Kalenderdatei versendet. Ein Entwurf versendet nichts.') }}</p>
      <label class="wm-check"><input v-model="reminder" type="checkbox" />{{ t('Erinnerung 24 Stunden vorher senden') }}</label>
      <label class="wm-check"><input v-model="notifyReady" type="checkbox" />{{ t('Kunden per E-Mail benachrichtigen, wenn der Meister „Abholbereit“ markiert') }}</label><p class="wm-muted">{{ t('Benötigt eine Kunden-E-Mail. Pro Termin und E-Mail-Adresse wird die Fertigmeldung einmal versendet. Die Nachricht verwendet die Vorlage aus den Einstellungen.') }}</p>
      <label v-if="!reception">{{ t('Stand des Termins') }}<select v-model="stage"><option value="planned">{{ t('Bestätigt – Fahrzeug noch nicht eingetroffen') }}</option><option value="arrived">{{ t('Fahrzeug eingetroffen') }}</option><option value="parts_missing">{{ t('Teile fehlen') }}</option><option value="customer_question">{{ t('Rückfrage beim Kunden') }}</option><option value="ready">{{ t('Abholbereit') }}</option><option value="collected">{{ t('Abgeholt') }}</option><option value="no_show">{{ t('Nicht erschienen') }}</option></select></label>
      <label v-if="!reception">{{ t('Interne Hinweise') }}<textarea v-model="note" maxlength="10000" rows="3"></textarea></label>
      <ReceptionChecklist v-if="!reception" v-model="checklist" />
      <label v-if="!reception">{{ t('Begründung für Überbuchung (nur bei Bedarf)') }}<textarea v-model="reason" maxlength="2000" rows="2"></textarea></label>
      <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
      <footer class="wm-actions wm-sticky-actions"><NcButton v-if="!original || original.status === 'draft'" :disabled="busy" @click="save('draft')">{{ t('Entwurf speichern') }}</NcButton><NcButton v-if="original && original.status !== 'cancelled'" :disabled="busy" @click="askCancel">{{ t('Termin absagen') }}</NcButton><NcButton variant="primary" :disabled="busy || original?.status === 'cancelled'" type="submit">{{ busy ? t('Wird gespeichert …') : original ? t('Terminänderungen speichern') : t('Termin speichern & bestätigen') }}</NcButton></footer>
    </form>
    <EntityEditor v-if="inlineEditor" :key="inlineEditor" :kind="inlineEditor" :customer-id="inlineEditor === 'vehicles' ? customerId : undefined" @close="inlineEditor = null" @saved="inlineSaved" />
  </Modal>
</template>
