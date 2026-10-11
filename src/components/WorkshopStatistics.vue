<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import Modal from './Modal.vue'
import { get, post, message, url } from '../api'
import { t, date, statusText, orderNumber } from '../format'
import { useDirtySnapshot, confirmDiscard } from '../dirty'
withDefaults(defineProps<{ active?: boolean }>(), { active: true })
const emit = defineEmits<{ order: [id: string]; appointments: [] }>()
import type { WorkshopReport, ReportJob } from '../types'
const localDay = (d: Date) =>
  new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Berlin' }).format(d)
const today = localDay(new Date())
const from = ref(today.slice(0, 7) + '-01'),
  to = ref(today),
  scope = ref('all')
const report = ref<WorkshopReport | null>(null),
  busy = ref(false),
  error = ref(''),
  notice = ref('')
const selected = ref<ReportJob | null>(null),
  planned = ref(''),
  actual = ref(''),
  revenueDate = ref(today),
  reason = ref('')
const query = ref(''),
  onlyMissing = ref(false),
  rowLimit = ref(50)
const { dirty, reset } = useDirtySnapshot(() =>
  selected.value
    ? {
        planned: planned.value,
        actual: actual.value,
        date: revenueDate.value,
        reason: reason.value,
      }
    : null,
)
function clearEditor() { selected.value = null; reset() }
function closeEditor() {
  if (!busy.value && confirmDiscard(dirty.value)) {
    selected.value = null
    reset()
  }
}
let sequence = 0
const euros = (cents: number) =>
  new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100)
const hours = (seconds: number) =>
  new Intl.NumberFormat('de-DE', { maximumFractionDigits: 2 }).format(seconds / 3600) +
  ' ' +
  t('Std.')
const percent = computed(() =>
  report.value?.summary.capacity_minutes
    ? Math.round(
        (report.value.summary.booked_minutes * 100) / report.value.summary.capacity_minutes,
      )
    : 0,
)
const jobs = computed(() =>
  (report.value?.jobs ?? []).filter((j) => {
    const missing =
      j.vehicle_kind === 'customer' &&
      (j.status === 'completed'
        ? j.valuation?.actual_net_cents == null
        : j.valuation?.planned_net_cents == null)
    return (
      (!onlyMissing.value || missing) &&
      `${j.title} ${j.vehicle_label}`.toLocaleLowerCase().includes(query.value.toLocaleLowerCase())
    )
  }),
)
const invalidDates = computed(
  () =>
    !from.value ||
    !to.value ||
    to.value < from.value ||
    (Date.parse(to.value) - Date.parse(from.value)) / 86400000 > 366,
)
async function load() {
  if (invalidDates.value || selected.value) return
  const current = ++sequence
  busy.value = true
  error.value = ''
  try {
    const data = await get<WorkshopReport>(
      `/api/v1/statistics?${new URLSearchParams({ from: from.value, to: to.value, scope: scope.value })}`,
    )
    if (current === sequence) {
      report.value = data
      rowLimit.value = 50
    }
  } catch (e) {
    if (current === sequence) {
      error.value = message(e)
      report.value = null
    }
  } finally {
    if (current === sequence) busy.value = false
  }
}
function preset(kind: 'month' | 'last' | 'year' | 'future') {
  if (kind === 'year') {
    from.value = today.slice(0, 4) + '-01-01'
    to.value = today
  } else if (kind === 'future') {
    from.value = today
    const d = new Date(today + 'T12:00:00Z')
    d.setUTCDate(d.getUTCDate() + 30)
    to.value = d.toISOString().slice(0, 10)
  } else if (kind === 'last') {
    const d = new Date(today + 'T12:00:00Z')
    d.setUTCDate(1)
    d.setUTCDate(0)
    to.value = d.toISOString().slice(0, 10)
    from.value = to.value.slice(0, 7) + '-01'
  } else {
    from.value = today.slice(0, 7) + '-01'
    to.value = today
  }
  void load()
}
function edit(job: ReportJob) {
  selected.value = job
  error.value = ''
  notice.value = ''
  planned.value =
    job.valuation?.planned_net_cents == null
      ? ''
      : (job.valuation.planned_net_cents / 100).toFixed(2)
  actual.value =
    job.valuation?.actual_net_cents == null ? '' : (job.valuation.actual_net_cents / 100).toFixed(2)
  revenueDate.value =
    job.valuation?.revenue_date ?? (job.completed_at ? localDay(new Date(job.completed_at)) : today)
  reason.value = ''
  reset()
}
function cents(input: string): number | null {
  if (input.trim() === '') return null
  if (!/^\d+(?:[.,]\d{1,2})?$/.test(input.trim()))
    throw new Error(
      t('Bitte einen positiven Eurobetrag mit höchstens zwei Nachkommastellen eingeben.'),
    )
  const value = Math.round(Number(input.replace(',', '.')) * 100)
  if (!Number.isSafeInteger(value) || value > 1000000000) throw new Error(t('Betrag ist zu groß.'))
  return value
}
async function save() {
  if (!selected.value || busy.value) return
  busy.value = true
  error.value = ''
  try {
    await post('/api/v1/statistics/revenue', {
      data: {
        source_kind: selected.value.source_kind,
        source_id: selected.value.source_id,
        version: selected.value.valuation?.version ?? 0,
        planned_net_cents: cents(planned.value),
        actual_net_cents: selected.value.status === 'completed' ? cents(actual.value) : null,
        revenue_date: revenueDate.value,
        reason: reason.value,
      },
    })
    selected.value = null
    reset()
    notice.value = t('Umsatz gespeichert. Die Auswertung wurde aktualisiert.')
    await load()
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
function printReport() {
  window.print()
}
onMounted(load)
onBeforeUnmount(() => {
  sequence++
})
</script>
<template>
  <section class="wm-statistics" :aria-busy="busy">
    <h1 class="wm-report-print-title">{{ t('Werkstattauswertung') }}</h1>
    <form class="wm-report-filter" @submit.prevent="load">
      <label>{{ t('Von') }}<input v-model="from" type="date" required :disabled="busy" /></label>
      <label>{{ t('Bis') }}<input v-model="to" type="date" required :disabled="busy" /></label>
      <label>{{ t('Arbeitszeiten und Aufträge')
        }}<select v-model="scope" :disabled="busy">
          <option value="all">{{ t('Gesamte Werkstatt') }}</option>
          <option value="customer">{{ t('Kundenfahrzeuge') }}</option>
          <option value="fleet">{{ t('Eigene Flotte') }}</option>
        </select></label>
      <NcButton :disabled="busy || invalidDates" @click="load">
{{
        busy ? t('Wird geladen …') : t('Auswertung anzeigen')
      }}
</NcButton>
    </form>
    <p v-if="invalidDates" role="alert">
      {{ t('Bitte einen gültigen Zeitraum von höchstens einem Jahr wählen.') }}
    </p>
    <div class="wm-actions wm-report-tools">
      <NcButton :disabled="busy" @click="preset('month')">{{ t('Dieser Monat') }}</NcButton><NcButton :disabled="busy" @click="preset('last')">{{ t('Letzter Monat') }}</NcButton><NcButton :disabled="busy" @click="preset('year')">{{ t('Dieses Jahr') }}</NcButton><NcButton :disabled="busy" @click="preset('future')">{{ t('Nächste 30 Tage') }}</NcButton><NcButton :disabled="!report || busy" @click="printReport">{{ t('Drucken') }}</NcButton><a :href="url('/api/v1/export/revenues')">{{ t('Alle erfassten Umsatzbeträge als CSV') }}</a>
    </div>
    <p class="wm-guide">
      {{
        t(
          'Alle Beträge in Euro netto. Ist-Umsatz ist der von Ihnen erfasste abgerechnete Auftragsbetrag am Umsatzdatum. Planumsatz ist eine Erwartung, kein Zahlungseingang. Bereits abgerechnete Nacharbeiten erzeugen keinen zweiten Planumsatz. Ist-Umsatz zählt nur bei aktuell abgeschlossenen Aufträgen. Die App ersetzt keine Buchhaltung. Fehlende Beträge zählen nicht als 0 €. Eigene Flotte erzeugt keinen Kundenumsatz.',
        )
      }}
    </p>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <template v-if="report">
      <p>
        {{ t('Angezeigter Zeitraum') }}:
        <strong>{{ date(report.from) }} – {{ date(report.to) }}</strong>
      </p>
      <div class="wm-report-cards">
        <article>
          <h2>{{ t('Erfasster Ist-Umsatz') }}</h2>
          <strong>{{ euros(report.summary.actual_cents) }}</strong>
          <p>{{ t('Abgeschlossene Kundenaufträge mit Umsatzdatum im Zeitraum') }}</p>
          <p>
            {{ report.summary.missing_actual }}
            {{ t('im Zeitraum abgeschlossene Kundenaufträge ohne Istbetrag') }}
          </p>
        </article>
        <article>
          <h2>{{ t('Geplanter Umsatz') }}</h2>
          <strong>{{ euros(report.summary.planned_cents) }}</strong>
          <p>{{ report.summary.planned_jobs }} {{ t('offene Aufträge / bestätigte Termine') }}</p>
          <p>{{ report.summary.missing_plan }} {{ t('Kundenvorgänge ohne Planbetrag') }}</p>
        </article>
        <article>
          <h2>{{ t('Erfasste Arbeitszeit') }}</h2>
          <strong>{{ hours(report.summary.work_seconds) }}</strong>
          <p>{{ t('Davon eigene Flotte') }}: {{ hours(report.summary.fleet_seconds) }}</p>
          <p>{{ t('Laufende Buchungen bis zur letzten Aktualisierung; Pausen zählen nicht.') }}</p>
        </article>
        <article>
          <h2>{{ t('Geplante Werkstattauslastung') }}</h2>
          <strong>{{ report.summary.capacity_minutes ? percent + ' %' : t('Kein Stundenbudget') }}</strong>
          <p>
            {{ hours(report.summary.booked_minutes * 60) }} /
            {{ hours(report.summary.capacity_minutes * 60) }}
          </p>
          <p>
            {{
              t(
                'Gesamte Werkstatt, unabhängig vom Fahrzeugfilter. Geplante Stunden geteilt durch Tagesbudgets; keine Anwesenheitsquote.',
              )
            }}
          </p>
        </article>
      </div>
      <p v-if="report.summary.undated_jobs" class="wm-guide">
        {{ report.summary.undated_jobs }}
        {{
          t(
            'offene Aufträge haben noch keinen Planungstag. Sie stehen unten zur Bearbeitung, fließen aber nicht in den Planumsatz dieses Zeitraums ein.',
          )
        }}
      </p>
      <p v-if="report.summary.long_intervals" role="status">
        {{ report.summary.long_intervals }}
        {{ t('Zeitbuchungen dauern mindestens 10 Stunden. Bitte im jeweiligen Auftrag prüfen.') }}
      </p>
      <h2>{{ t('Umsatz nach Monat') }}</h2>
      <div class="wm-report-table">
        <table>
          <thead>
            <tr>
              <th scope="col">{{ t('Monat') }}</th>
              <th scope="col">{{ t('Ist-Umsatz netto') }}</th>
              <th scope="col">{{ t('Planumsatz netto') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in report.months" :key="m.month">
              <th scope="row">
                {{
                  new Intl.DateTimeFormat('de-DE', {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                  }).format(new Date(m.month + '-01T12:00:00Z'))
                }}
              </th>
              <td>{{ euros(m.actual_cents) }}</td>
              <td>{{ euros(m.planned_cents) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <h2>{{ t('Auftragsbeträge erfassen') }}</h2>
      <p>
        {{
          t(
            'Öffnen Sie „Beträge erfassen“. Tragen Sie bei geplanten Arbeiten den vereinbarten Netto-Auftragswert ein, nach Abschluss den abgerechneten Netto-Gesamtbetrag. Mehrtages-Planwerte werden nach Arbeitsstunden aufgeteilt.',
          )
        }}
      </p>
      <div class="wm-report-filter">
        <label>{{ t('Auftrag oder Fahrzeug suchen') }}<input v-model="query" type="search" /></label><label><input v-model="onlyMissing" type="checkbox" />{{ t('Nur fehlende Beträge') }}</label>
      </div>
      <p v-if="!jobs.length">{{ t('Keine passenden Vorgänge im Zeitraum.') }}</p>
      <div v-else class="wm-report-table">
        <table>
          <thead>
            <tr>
              <th scope="col">{{ t('Vorgang / Fahrzeug') }}</th>
              <th scope="col">{{ t('Status / Tag') }}</th>
              <th scope="col">{{ t('Planbetrag gesamt') }}</th>
              <th scope="col">{{ t('Istbetrag / Umsatzdatum') }}</th>
              <th scope="col">{{ t('Aktion') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="j in jobs.slice(0, rowLimit)" :key="j.source_kind + j.source_id">
              <th scope="row">
                {{ j.title }}<br />{{ j.vehicle_label
                }}<small>{{
                  j.source_kind === 'orders' ? orderNumber(j.source_id) : t('Kundentermin')
                }}</small>
              </th>
              <td>
                {{ statusText(j.status) }}<br />{{
                  j.date ? date(j.date) : t('Noch kein Planungstag')
                }}
              </td>
              <td>
                {{
                  j.valuation?.planned_net_cents == null
                    ? t('Nicht erfasst')
                    : euros(j.valuation.planned_net_cents)
                }}
              </td>
              <td>
                {{
                  j.valuation?.actual_net_cents == null
                    ? t('Nicht erfasst')
                    : euros(j.valuation.actual_net_cents)
                }}<br />{{ j.valuation?.revenue_date ? date(j.valuation.revenue_date) : '' }}
              </td>
              <td>
                <NcButton v-if="j.vehicle_kind === 'customer'" :disabled="busy" @click="edit(j)">
{{
                  t('Beträge erfassen')
                }}
</NcButton><span v-else>{{ t('Interne Flottenarbeit') }}</span>
                <NcButton v-if="j.source_kind === 'orders'" @click="emit('order', j.source_id)">{{ t('Auftrag öffnen') }}</NcButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <NcButton v-if="jobs.length > rowLimit" @click="rowLimit += 50">
{{
        t('Weitere Vorgänge anzeigen')
      }}
</NcButton>
      <h2>{{ t('Arbeitszeit je Mitarbeiter') }}</h2>
      <p>
        {{
          t(
            'Gebuchte Arbeitszeit im Zeitraum, einschließlich laufender Buchungen. Keine Bewertung von Anwesenheit oder Leistung.',
          )
        }}
      </p>
      <p v-if="!report.employees.length">
        {{ t('Noch keine Arbeitszeit in diesem Zeitraum gebucht.') }}
      </p>
      <div v-else class="wm-report-table">
        <table>
          <thead>
            <tr>
              <th scope="col">{{ t('Mitarbeiter') }}</th>
              <th scope="col">{{ t('Arbeitszeit') }}</th>
              <th scope="col">{{ t('Davon Flotte') }}</th>
              <th scope="col">{{ t('Bearbeitete Aufträge') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in report.employees" :key="e.id">
              <th scope="row">
                {{ e.name === 'Name nicht mehr verfügbar' ? t('Name nicht mehr verfügbar') : e.name
                }}<small v-if="e.running">{{ t('Zeitbuchung läuft') }}</small>
              </th>
              <td>{{ hours(e.seconds) }}</td>
              <td>{{ hours(e.fleet_seconds) }}</td>
              <td>{{ e.orders }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <h2>{{ t('Soll und Ist abgeschlossener Aufträge') }}</h2>
      <p>
        {{ report.summary.completed }}
        {{
          t(
            'Aufträge im Zeitraum abgeschlossen. Der Vergleich umfasst die gesamte Arbeitszeit dieser Aufträge, auch vor dem Zeitraum.',
          )
        }}
      </p>
      <p v-if="report.summary.comparable_completed">
        {{ t('Aufträge mit Sollzeit') }}: {{ report.summary.comparable_completed }} ·
        {{ t('Soll') }} {{ hours(report.summary.completed_planned_minutes * 60) }} · {{ t('Ist') }}
        {{ hours(report.summary.completed_work_seconds) }} · {{ t('Abweichung') }}
        {{
          hours(
            report.summary.completed_work_seconds - report.summary.completed_planned_minutes * 60,
          )
        }}
      </p>
      <div v-if="report.completed.length" class="wm-report-table">
        <table>
          <thead>
            <tr>
              <th scope="col">{{ t('Auftrag') }}</th>
              <th scope="col">{{ t('Sollzeit') }}</th>
              <th scope="col">{{ t('Arbeitszeit gesamt') }}</th>
              <th scope="col">{{ t('Abweichung') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in report.completed" :key="o.id">
              <th scope="row">{{ o.title }}</th>
              <td>{{ o.minutes == null ? t('Keine Sollzeit') : hours(o.minutes * 60) }}</td>
              <td>{{ hours(o.seconds) }}</td>
              <td>{{ o.minutes == null ? '–' : hours(o.seconds - o.minutes * 60) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <h2>{{ t('Tagesauslastung der gesamten Werkstatt') }}</h2>
      <NcButton @click="emit('appointments')">{{ t('Terminbuch öffnen / Stundenbudget ändern') }}</NcButton>
      <div class="wm-report-table">
        <table>
          <thead>
            <tr>
              <th scope="col">{{ t('Tag') }}</th>
              <th scope="col">{{ t('Verplant / verfügbar') }}</th>
              <th scope="col">{{ t('Auslastung') }}</th>
              <th scope="col">{{ t('Fahrzeuge') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in report.days" :key="d.date">
              <th scope="row">
                {{
                  new Intl.DateTimeFormat('de-DE', {
                    weekday: 'short',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    timeZone: 'UTC',
                  }).format(new Date(d.date + 'T12:00:00Z'))
                }}
              </th>
              <td>{{ hours(d.used * 60) }} / {{ hours(d.minutes * 60) }}</td>
              <td>
                <meter
                  v-if="d.minutes"
                  min="0"
                  max="100"
                  :value="Math.min(100, d.percent)"
                  :aria-label="t('Geplante Tagesauslastung')"
                />
                {{ d.percent }} % <span v-if="d.overbooked">{{ t('Überbucht') }}</span><span v-if="d.closed">{{ t('Geschlossen') }}</span>
              </td>
              <td>
                {{ d.vehicle_count }}{{ d.max_vehicles != null ? ' / ' + d.max_vehicles : '' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
    <Modal
      v-if="selected"
      :active="active"
      :dirty="dirty"
      :locked="busy"
      :title="t('Auftragsbeträge erfassen')"
      @close="clearEditor"
    >
      <p>
        <strong>{{ selected.title }}</strong> · {{ selected.vehicle_label }}
      </p>
      <p>
        {{
          t(
            'Gesamtbetrag des Vorgangs in Euro netto, einschließlich Material und Fremdleistungen. Leer bedeutet unbekannt; 0 bedeutet ausdrücklich ohne Umsatz. Katalogänderungen ändern diese Beträge nicht.',
          )
        }}
      </p>
      <form @submit.prevent="save">
        <label>{{ t('Geplanter Netto-Gesamtbetrag (€)')
          }}<input
            v-model="planned"
            inputmode="decimal"
            :disabled="busy"
            :placeholder="t('z. B. 250,00')"
        /></label>
        <template v-if="selected.status === 'completed'">
<label>{{ t('Abgerechneter Netto-Gesamtbetrag (€)')
            }}<input v-model="actual" inputmode="decimal" :disabled="busy" /></label><label>{{ t('Umsatzdatum')
            }}<input
              v-model="revenueDate"
              type="date"
              :required="actual !== ''"
              :disabled="busy"
/></label>
</template>
        <p v-else>{{ t('Ist-Umsatz kann nach Meisterabnahme des Auftrags erfasst werden.') }}</p>
        <label>{{ t('Notiz / Grund der Erfassung oder Korrektur')
          }}<textarea v-model="reason" required maxlength="1000" :disabled="busy" />
        </label>
        <p v-if="error" role="alert">{{ error }}</p>
        <NcButton :disabled="busy || !reason.trim()" @click="save">
{{
          t('Beträge speichern')
        }}
</NcButton><NcButton :disabled="busy" @click="closeEditor">{{ t('Abbrechen') }}</NcButton>
      </form>
    </Modal>
  </section>
</template>
<style scoped>
.wm-report-print-title {
  display: none;
}
.wm-report-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: end;
  margin-bottom: 1rem;
}
.wm-report-filter label {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}
.wm-report-filter input[type='checkbox'] {
  width: auto;
}
.wm-report-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 1rem;
}
.wm-report-cards article {
  padding: 1rem;
  background: var(--color-background-dark);
  border-radius: var(--border-radius-large);
}
.wm-report-cards strong {
  font-size: 1.6rem;
}
h2 {
  margin: 1.5rem 0 0.6rem;
  font-size: 1.2rem;
}
.wm-report-cards h2 {
  margin-top: 0;
}
.wm-report-table {
  overflow-x: auto;
  margin: 1rem 0;
}
table {
  border-collapse: collapse;
  width: 100%;
}
th,
td {
  text-align: start;
  padding: 0.7rem;
  border-bottom: 1px solid var(--color-border);
  vertical-align: top;
}
small {
  display: block;
  font-weight: normal;
}
form label {
  display: block;
  margin-bottom: 0.7rem;
}
form input,
form select,
form textarea {
  display: block;
  max-width: 100%;
}
@media print {
  .wm-report-print-title { display: block; }
  .wm-report-filter,
  .wm-report-tools,
  button {
    display: none !important;
  }
  .wm-report-table {
    overflow: visible;
  }
  article,
  tr {
    break-inside: avoid;
  }
}
</style>
