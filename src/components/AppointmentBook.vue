<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { bookRange, shiftBookDay, appointmentMinutesOn, matchesAppointment } from '../planning'
import { statusText } from '../format'
import { get, post, command, message } from '../api'
import { t, date, stamp, vehicleLabel } from '../format'
import type { Appointment, BookView, Vehicle, Order, Delivery, CapacityLimit, CapacityDay } from '../types'
import AppointmentEditor from './AppointmentEditor.vue'
import { berlinInput } from '../services'
const props = defineProps<{ reception?: boolean }>()
const emit = defineEmits<{ order: [Order] }>()
const selectedDay = ref(new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Berlin' })), mode = ref('week'), book = ref<BookView | null>(null), vehicles = ref<Record<string, Vehicle>>({})
const linkedOrders = ref<Record<string, Order>>({}), notice = ref('')
const query = ref(''), filter = ref('all'), copyFrom = ref<Appointment | undefined>()
const visibleAppointments = computed(() => book.value?.items.filter(a => matchesAppointment(a, vehicles.value[a.vehicle_id], query.value, filter.value)) ?? [])
const waitingCount = computed(() => book.value?.items.filter(a => a.status === 'confirmed' && ['parts_missing', 'customer_question'].includes(a.stage)).length ?? 0)
const readyCount = computed(() => book.value?.items.filter(a => a.status === 'confirmed' && a.stage === 'ready').length ?? 0)
const error = ref(''), busy = ref(false), editing = ref<Appointment | null>(null), editor = ref(false), capacityOpen = ref(false), deliveries = ref<Delivery[]>([]), submitted = ref<Delivery[]>([])
const week = ref<Record<string, CapacityLimit>>({}), dayOverride = ref<CapacityDay | null>(null)
const weekdays = [t('Montag'), t('Dienstag'), t('Mittwoch'), t('Donnerstag'), t('Freitag'), t('Samstag'), t('Sonntag')]
const stageLabels: Record<string, string> = { planned: t('Bestätigt'), arrived: t('Fahrzeug eingetroffen'), parts_missing: t('Teile fehlen'), customer_question: t('Rückfrage beim Kunden'), ready: t('Abholbereit'), collected: t('Abgeholt'), no_show: t('Nicht erschienen') }
const range = computed(() => bookRange(selectedDay.value, mode.value))
let timer: ReturnType<typeof setInterval> | undefined, revision = 0
async function load() {
  if (range.value.length !== 2) { ++revision; error.value = t('Bitte ein gültiges Datum auswählen.'); return }
  const n = ++revision
  try {
    const [result, failures] = await Promise.all([get<BookView>(`/api/v1/appointments?from=${range.value[0]}&to=${range.value[1]}`), props.reception ? Promise.resolve({ items: [] as Delivery[], submitted: [] as Delivery[] }) : get<{ items: Delivery[]; submitted?: Delivery[] }>('/api/v1/deliveries')])
    if (n !== revision) return
    book.value = result; deliveries.value = failures.items; submitted.value = failures.submitted ?? []
    const ids = [...new Set(result.items.map(a => a.vehicle_id))]
    for (let i = 0; i < ids.length; i += 100) { const p = await get<{ items: Vehicle[] }>(`/api/v1/records/vehicles?ids=${ids.slice(i, i + 100).join(',')}`); if (n !== revision) return; p.items.forEach(v => vehicles.value[v.id] = v) }
    const orderIds = props.reception ? [] : [...new Set(result.items.map(a => a.order_id).filter(Boolean))]
    for (let i = 0; i < orderIds.length; i += 100) { const p = await get<{ items: Order[] }>(`/api/v1/records/orders?ids=${orderIds.slice(i,i+100).join(',')}`); if (n !== revision) return; p.items.forEach(o => linkedOrders.value[o.id] = o) }
    error.value = ''
  } catch (e) { error.value = message(e) }
}
onMounted(() => { void load(); timer = setInterval(() => { if (!document.hidden && !editor.value && !capacityOpen.value && !dayOverride.value && !busy.value) void load() }, 10000) })
onBeforeUnmount(() => { clearInterval(timer); ++revision })
function appointmentsFor(day: string) { return visibleAppointments.value.filter(a => new Date(a.arrival).toLocaleDateString('sv-SE', { timeZone: 'Europe/Berlin' }) === day || a.allocations.some(d => d.date === day)) }
function edit(a?: Appointment, day?: string) { if (day) selectedDay.value = day; copyFrom.value = undefined; editing.value = a ?? null; editor.value = true }
function followUp(a: Appointment) { editing.value = null; copyFrom.value = a; editor.value = true }
async function saved(a?: Appointment) {
  editor.value = false
  if (a?.arrival) selectedDay.value = berlinInput(a.arrival).slice(0, 10)
  query.value = ''; filter.value = 'all'
  notice.value = a?.status === 'draft' ? t('Entwurf gespeichert. Keine Kapazität gebucht und keine Nachricht versendet.') : a?.status === 'cancelled' ? t('Termin abgesagt.') : t('Termin gespeichert und bestätigt. Kalender- und E-Mail-Zustellung werden getrennt verarbeitet.')
  await load()
}
async function convert(a: Appointment) { busy.value = true; try { const o = await post<Order>(`/api/v1/appointments/${a.id}/order`, { version: a.version }); emit('order', o); await load() } catch (e) { error.value = message(e) } finally { busy.value = false } }
function overrideDay(d: CapacityDay) { dayOverride.value = JSON.parse(JSON.stringify(d)) }
function openCapacity() { week.value = JSON.parse(JSON.stringify(book.value?.settings.settings.weekdays ?? {})); capacityOpen.value = true }
async function saveCapacity() {
  busy.value = true
  try { await post('/api/v1/capacity', { data: { weekdays: week.value, version: book.value?.settings.version } }); capacityOpen.value = false; await load() } catch (e) { error.value = message(e) } finally { busy.value = false }
}
async function saveDay() {
  const d = dayOverride.value; if (!d) return
  busy.value = true
  try { await post('/api/v1/capacity', { data: { date: d.date, minutes: d.minutes, max_vehicles: d.max_vehicles, closed: d.closed, version: d.override?.version } }); dayOverride.value = null; await load() } catch (e) { error.value = message(e) } finally { busy.value = false }
}
async function retry(d: Delivery) { busy.value = true; try { await post(`/api/v1/deliveries/${d.id}/retry`, { version: d.version }); await load() } catch (e) { error.value = message(e) } finally { busy.value = false } }
async function setStage(a: Appointment, stage: Appointment['stage']) {
  if (busy.value) return
  if (stage === 'collected' && !window.confirm(t('Die tatsächliche Fahrzeugabholung bestätigen?'))) return
  busy.value = true; error.value = ''; notice.value = ''
  try { await command('/api/v1/appointments', { ...a, stage }); notice.value = t('Terminstand gespeichert:') + ' ' + stageLabels[stage]; await load() } catch (e) { error.value = message(e) } finally { busy.value = false }
}
function move(direction: number) { selectedDay.value = shiftBookDay(selectedDay.value, mode.value, direction); void load() }
function print() { window.print() }
</script>
<template>
  <section class="wm-book">
    <div class="wm-actions wm-book-toolbar"><NcButton variant="primary" @click="edit()">+ {{ t('Kundentermin anlegen') }}</NcButton><NcButton @click="selectedDay = new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Berlin' }); load()">{{ t('Heute') }}</NcButton><NcButton @click="move(-1)">← {{ t('Zurück') }}</NcButton><NcButton @click="move(1)">{{ t('Weiter') }} →</NcButton><label>{{ t('Datum') }}<input v-model="selectedDay" type="date" @change="load" /></label><label>{{ t('Ansicht') }}<select v-model="mode" @change="load"><option value="day">{{ t('Tag') }}</option><option value="week">{{ t('Woche') }}</option><option value="month">{{ t('Monat') }}</option></select></label><NcButton v-if="!reception" @click="openCapacity">{{ t('Tageskapazität einstellen') }}</NcButton><NcButton @click="print">{{ t('Tagesübersicht drucken') }}</NcButton></div>
    <p class="wm-guide">{{ t('Stundenbudget für alle geplanten Kunden- und Flottenarbeiten. Ein Termin mit Arbeitsauftrag zählt nur einmal. Arbeitsstunden sind keine automatisch vergebenen Mechanikertermine.') }}</p>
    <div class="wm-actions wm-book-toolbar"><label>{{ t('Termine suchen') }}<input v-model="query" type="search" :placeholder="t('Kennzeichen, Arbeit, FIN oder E-Mail')" /></label><label>{{ t('Termine anzeigen') }}<select v-model="filter"><option value="all">{{ t('Alle Termine') }}</option><option value="confirmed">{{ t('Bestätigt') }}</option><option value="draft">{{ t('Entwürfe') }}</option><option value="waiting">{{ t('Warten auf Teile oder Kundenantwort') }}</option><option value="ready">{{ t('Abholbereit') }}</option><option value="cancelled">{{ t('Abgesagt') }}</option></select></label><NcButton @click="filter = 'waiting'">{{ t('Warten auf Antwort / Teile') }}: {{ waitingCount }}</NcButton><NcButton @click="filter = 'ready'">{{ t('Abholbereit') }}: {{ readyCount }}</NcButton><NcButton v-if="query || filter !== 'all'" @click="query = ''; filter = 'all'">{{ t('Alle wieder anzeigen') }}</NcButton></div>
    <p role="status">{{ visibleAppointments.length }} {{ t('passende Termine im gewählten Zeitraum. Auslastung zählt weiterhin alle Arbeiten.') }}</p>
    <p v-if="notice" role="status" class="wm-success">{{ notice }}</p>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
    <div v-if="capacityOpen" class="wm-card wm-form">
<h2>{{ t('Verfügbare Arbeitszeit je Wochentag') }}</h2><p>{{ t('Minuten aller verfügbaren Mechaniker zusammen. Zum Beispiel zwei Mechaniker mit je 6 Stunden: 720 Minuten. Das anfängliche Budget von 480 Minuten bitte anpassen.') }}</p>
      <div v-for="(name, i) in weekdays" :key="i" class="wm-capacity-row"><strong>{{ name }}</strong><label>{{ t('Arbeitsminuten') }}<input v-if="week[String(i + 1)]" v-model.number="week[String(i + 1)].minutes" type="number" min="0" /></label><label>{{ t('Maximale Fahrzeuge (optional)') }}<input v-if="week[String(i + 1)]" v-model.number="week[String(i + 1)].max_vehicles" type="number" min="1" /></label><label v-if="week[String(i + 1)]" class="wm-check"><input v-model="week[String(i + 1)].closed" type="checkbox" />{{ t('Geschlossen') }}</label></div>
      <div class="wm-actions"><NcButton :disabled="busy" @click="capacityOpen = false">{{ t('Abbrechen') }}</NcButton><NcButton :disabled="busy" variant="primary" @click="saveCapacity">{{ t('Kapazität speichern') }}</NcButton></div>
    </div>
    <div v-if="dayOverride" class="wm-card wm-form"><h2>{{ t('Abweichende Kapazität') }} · {{ date(dayOverride.date) }}</h2><label>{{ t('Arbeitsminuten') }}<input v-model.number="dayOverride.minutes" type="number" min="0" /></label><label>{{ t('Maximale Fahrzeuge') }}<input v-model.number="dayOverride.max_vehicles" type="number" min="1" /></label><label class="wm-check"><input v-model="dayOverride.closed" type="checkbox" />{{ t('Geschlossen') }}</label><div class="wm-actions"><NcButton @click="dayOverride = null">{{ t('Abbrechen') }}</NcButton><NcButton :disabled="busy" @click="saveDay">{{ t('Tag speichern') }}</NcButton></div></div>
    <div class="wm-book-days" :class="{ 'wm-book-month': mode === 'month' }">
      <article v-for="d in book?.days" :key="d.date" class="wm-card wm-book-day" :class="{ 'wm-overbooked': d.overbooked, 'wm-not-print-day': d.date !== selectedDay }">
<h2>{{ new Date(d.date + 'T12:00:00Z').toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }) }}</h2><p><strong>{{ (d.used / 60).toLocaleString('de-DE', { maximumFractionDigits: 1 }) }} {{ t('von') }} {{ (d.minutes / 60).toLocaleString('de-DE', { maximumFractionDigits: 1 }) }} {{ t('Stunden verplant') }}</strong></p><p>{{ (d.free / 60).toLocaleString('de-DE', { maximumFractionDigits: 1 }) }} {{ t('Stunden frei') }} · {{ d.percent }} % · {{ d.vehicle_count }} {{ t('Fahrzeuge') }}{{ d.max_vehicles ? ' / ' + d.max_vehicles : '' }}</p><p v-if="d.closed">{{ t('Geschlossen') }}</p><p v-if="d.overbooked" role="status">{{ t('Überbucht – Tagesplanung prüfen') }}</p><progress :value="Math.min(100, d.percent)" max="100" :aria-label="t('Auslastung')"></progress>
        <div v-for="a in appointmentsFor(d.date)" :key="a.id" class="wm-appointment"><button class="wm-appointment-open" @click="edit(a)"><strong>{{ a.title }}</strong><span>{{ vehicles[a.vehicle_id] ? vehicleLabel(vehicles[a.vehicle_id]) : t('Fahrzeug wird geladen …') }}</span><span>{{ t('Ankunft') }}: {{ stamp(a.arrival) }}</span><span>{{ t('An diesem Tag') }}: {{ appointmentMinutesOn(a, d.date) ?? '–' }} min · {{ t('insgesamt') }} {{ a.minutes ?? '–' }} min</span><span v-if="a.order_id && linkedOrders[a.order_id]">{{ t('Arbeitsauftrag') }}: {{ statusText(linkedOrders[a.order_id].status) }}</span><span>{{ a.status === 'draft' ? t('Entwurf') : a.status === 'cancelled' ? t('Abgesagt') : stageLabels[a.stage] }}</span></button><p v-if="a.checklist?.callback_note" class="wm-guide">{{ t('Rückfrage') }}: {{ a.checklist.callback_note }}</p><NcButton :disabled="busy" @click="followUp(a)">{{ t('Folgetermin vorbereiten') }}</NcButton><NcButton v-if="!reception && a.status === 'confirmed'" :disabled="busy" @click="convert(a)">{{ a.order_id ? t('Arbeitsauftrag öffnen') : t('Arbeitsauftrag erstellen') }}</NcButton><div v-if="!reception && a.status === 'confirmed'" class="wm-actions"><NcButton v-if="a.stage === 'planned' || a.stage === 'no_show'" :disabled="busy" @click="setStage(a, 'arrived')">{{ t('Ankunft bestätigen') }}</NcButton><NcButton v-if="a.order_id && linkedOrders[a.order_id]?.status === 'completed' && !['ready','collected'].includes(a.stage)" :disabled="busy" @click="setStage(a, 'ready')">{{ t('Abholbereit setzen') }}</NcButton><NcButton v-if="a.stage === 'ready'" :disabled="busy" @click="setStage(a, 'collected')">{{ t('Abholung bestätigen') }}</NcButton></div></div>
        <p v-if="!appointmentsFor(d.date).length">{{ query || filter !== 'all' ? t('Keine passenden Termine für diesen Filter.') : t('Keine Kundentermine eingetragen.') }}</p><div class="wm-actions"><NcButton @click="edit(undefined, d.date)">+ {{ t('Termin') }}</NcButton><NcButton v-if="!reception" @click="overrideDay(d)">{{ t('Kapazität ändern') }}</NcButton></div>
      </article>
    </div>
    <details v-if="deliveries.length" class="wm-help"><summary>{{ t('Kalender und E-Mail: ausstehende Zustellungen') }} ({{ deliveries.length }})</summary><p>{{ t('Hintergrundjobs erledigen den Versand. Bei Fehlern bitte SMTP und Kalenderzugang prüfen. Ein unklarer Versandausgang kann bei erneuter Zustellung eine doppelte E-Mail verursachen.') }}</p><div v-for="d in deliveries" :key="d.id" class="wm-card"><p v-if="d.context"><strong>{{ d.context.title }}</strong> · {{ d.context.vehicle_label }} · {{ stamp(d.context.arrival) }}</p><p>{{ d.kind === 'ready' ? t('Abholbenachrichtigung') : d.kind === 'deadline' ? t('Fristenerinnerung') : d.channel === 'email' ? t('Termin-E-Mail') : t('Kalender') }} · {{ d.status === 'pending' ? t('Vorgemerkt') : d.status === 'uncertain' ? t('Versandausgang unklar') : t('Fehlgeschlagen') }} · {{ d.last_error }}</p><NcButton v-if="d.status !== 'pending'" :disabled="busy" @click="retry(d)">{{ t('Zustellung erneut versuchen') }}</NcButton></div></details>
    <details v-if="submitted.length" class="wm-help"><summary>{{ t('An Nextcloud Mail übergebene Nachrichten') }} ({{ submitted.length }})</summary><p>{{ t('Die letzten 20 Übergaben. Postausgang und tatsächlichen Versand im Mailclient prüfen. Eine Übergabe ist keine Zustellbestätigung; hier nicht nochmals senden.') }}</p><p v-for="d in submitted" :key="d.id">{{ d.context?.title }} · {{ stamp(d.updated_at) }} · {{ t('An Mail übergeben') }}</p></details>
    <AppointmentEditor v-if="editor" :reception="reception" :appointment="editing ?? undefined" :copy-from="copyFrom" :day="selectedDay" @close="editor = false" @saved="saved" />
  </section>
</template>
