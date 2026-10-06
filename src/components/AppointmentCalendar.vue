<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, message } from '../api'
import { t, stamp } from '../format'
import { bookRange, shiftBookDay } from '../planning'
import { berlinInput } from '../services'
import type { BookView, CapacityDay } from '../types'
const props = defineProps<{ day: string; minutes: number | null; appointmentId?: string; multiday?: boolean }>()
const emit = defineEmits<{ choose: [day: string] }>()
const browsing = ref(props.day), book = ref<BookView | null>(null), busy = ref(false), error = ref('')
const range = computed(() => bookRange(browsing.value, 'week'))
let revision = 0
async function load() {
  const n = ++revision; book.value = null; error.value = ''
  if (range.value.length !== 2) { error.value = t('Bitte ein gültiges Kalenderdatum wählen.'); busy.value = false; return }
  busy.value = true
  try { const r = await get<BookView>(`/api/v1/appointments?from=${range.value[0]}&to=${range.value[1]}`); if (n === revision) book.value = r }
  catch (e) { if (n === revision) error.value = message(e) }
  finally { if (n === revision) busy.value = false }
}
watch(() => props.day, day => { if (bookRange(day, 'week').join() !== range.value.join()) browsing.value = day })
watch(browsing, load, { immediate: true })
onBeforeUnmount(() => { ++revision })
function move(n: number) { browsing.value = shiftBookDay(browsing.value, 'week', n) }
function entries(day: string) {
  return (book.value?.items ?? []).filter(a => a.status !== 'cancelled' && (berlinInput(a.arrival).slice(0, 10) === day || a.allocations.some(d => d.date === day))).sort((a, b) => a.arrival.localeCompare(b.arrival) || a.id.localeCompare(b.id))
}
function label(day: string) { return new Date(day + 'T12:00:00Z').toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }) }
function hours(minutes: number) { return (minutes / 60).toLocaleString('de-DE', { maximumFractionDigits: 1 }) }
function fits(d: CapacityDay) {
  const a = book.value?.items.find(a => a.id === props.appointmentId && a.status === 'confirmed')
  const own = a?.allocations.filter(x => x.date === d.date).reduce((sum, x) => sum + x.minutes, 0) ?? 0
  return !d.closed && (props.minutes == null || d.free + own >= props.minutes) && (d.max_vehicles == null || d.vehicle_count < d.max_vehicles || (a?.vehicle_id && entries(d.date).some(x => x.id === a.id)))
}
</script>
<template>
  <section class="wm-intake-calendar" :aria-label="t('Kalender und freie Tageskapazität')" :aria-busy="busy">
    <h3>{{ t('Freien Termin im Kalender wählen') }}</h3>
    <p>{{ t('Jeder Tag zeigt vorhandene Termine und die gesamte verfügbare Arbeitszeit. Das Stundenbudget ist keine Zusage eines freien Mechanikers zur gewählten Uhrzeit.') }}</p>
    <div class="wm-actions"><NcButton :aria-label="t('Vorherige Kalenderwoche')" @click="move(-1)">← {{ t('Vorherige Woche') }}</NcButton><NcButton :aria-label="t('Nächste Kalenderwoche')" @click="move(1)">{{ t('Nächste Woche') }} →</NcButton><label>{{ t('Kalenderdatum') }}<input v-model="browsing" type="date" /></label><NcButton :disabled="busy" @click="load">{{ t('Auslastung aktualisieren') }}</NcButton></div>
    <p v-if="busy" role="status">{{ t('Termine und Auslastung werden geladen …') }}</p>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
    <p v-if="multiday">{{ t('Bei einer Mehrtagesplanung die Arbeitstage im Abschnitt „Arbeit auf mehrere Tage verteilen“ ändern.') }}</p>
    <div class="wm-intake-calendar-days">
      <article v-for="d in book?.days" :key="d.date" class="wm-card" :class="{ 'wm-day-selected': day === d.date }">
        <h4>{{ label(d.date) }}</h4>
        <p><strong>{{ d.closed ? t('Geschlossen') : t('{free} von {total} Stunden frei', { free: hours(d.free), total: hours(d.minutes) }) }}</strong></p>
        <p>{{ t('Auslastung: {percent} %', { percent: d.percent }) }} · {{ t('{count} Fahrzeuge', { count: d.vehicle_count }) }}{{ d.max_vehicles ? ' / ' + d.max_vehicles : '' }}</p>
        <progress :value="Math.min(d.percent, 100)" max="100" :aria-label="t('Tagesauslastung') + ': ' + label(d.date)"></progress>
        <p v-if="d.overbooked" class="wm-error">{{ t('Bereits überbucht') }}</p>
        <p v-else-if="!d.closed && minutes && !fits(d)">{{ t('Für diese Arbeiten nicht genügend Tageskapazität. Anderen Tag oder Mehrtagesplanung wählen.') }}</p>
        <ul v-if="entries(d.date).length" class="wm-calendar-entries"><li v-for="a in entries(d.date)" :key="a.id"><strong>{{ a.title }}</strong><br /><span>{{ berlinInput(a.arrival).slice(0, 10) === d.date ? t('Ankunft') + ': ' + stamp(a.arrival) : t('Arbeitstag – Ankunft') + ': ' + stamp(a.arrival) }}</span><br /><span v-if="a.pickup">{{ t('Abholung') }}: {{ stamp(a.pickup) }}</span><span v-if="a.status === 'draft'"> · {{ t('Entwurf – ohne Reservierung') }}</span><span v-if="a.id === appointmentId"> · {{ t('Dieser Termin') }}</span></li></ul>
        <p v-else>{{ t('Keine Kundentermine eingetragen.') }}</p>
        <NcButton :disabled="d.closed || multiday" :aria-pressed="day === d.date" @click="emit('choose', d.date)">{{ day === d.date ? t('Ausgewählter Ankunftstag') : t('Diesen Tag wählen') }}</NcButton>
      </article>
    </div>
    <p>{{ t('Die Auswahl übernimmt den Ankunftstag und behält die Ankunftsuhrzeit. Bei einem anderen Tag werden Arbeitsbeginn und Abholung geleert; bitte neu festlegen. Kapazität wird erst beim bestätigten Speichern reserviert und nochmals geprüft.') }}</p>
  </section>
</template>
