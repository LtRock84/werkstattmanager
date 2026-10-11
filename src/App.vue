<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { loadState } from '@nextcloud/initial-state'
import { isAxiosError } from '@nextcloud/axios'
import { all, list, get, post, url, message } from './api'
import { t, vehicleLabel, stamp, date, statusText, orderNumber, deadlineState } from './format'
import type {
  Role,
  Order,
  Vehicle,
  Employee,
  Active,
  Part,
  Defect,
  Deadline,
  Row,
  Editable,
  Table,
  Tables,
  Page,
  FailedNotification,
} from './types'
import CompanyHeader from './components/CompanyHeader.vue'
import CompanySettings from './components/CompanySettings.vue'
import DriverForm from './components/DriverForm.vue'
import AccessSettings from './components/AccessSettings.vue'
import AppointmentBook from './components/AppointmentBook.vue'
import ServiceCatalogue from './components/ServiceCatalogue.vue'
import WorkshopStatistics from './components/WorkshopStatistics.vue'
import PriceCalculator from './components/PriceCalculator.vue'
import MailSettings from './components/MailSettings.vue'
import CustomerMailSettings from './components/CustomerMailSettings.vue'
import CalendarSettings from './components/CalendarSettings.vue'
import OrderComposer from './components/OrderComposer.vue'
import OrderDetail from './components/OrderDetail.vue'
import VehicleDetail from './components/VehicleDetail.vue'
import DefectDetail from './components/DefectDetail.vue'
import EntityEditor from './components/EntityEditor.vue'
const role = loadState<Role>('werkstattmanager', 'mode', 'tablet'),
  master = role === 'master'
const pricingVisited = ref(false), statisticsVisited = ref(false), settingsVisited = ref(false)
const page = ref(master ? 'dashboard' : 'orders'),
  search = ref(''),
  status = ref(master ? '' : 'open'),
  employeeFilter = ref(''),
  fleetFilter = ref('')
const orders = ref<Order[]>([]),
  vehicles = ref<Vehicle[]>([]),
  employees = ref<Employee[]>([]),
  active = ref<Active[]>([]),
  parts = ref<Part[]>([]),
  defects = ref<Defect[]>([]),
  deadlines = ref<Deadline[]>([]),
  records = ref<Row[]>([])
const library = ref<Editable>('templates'),
  busy = ref(false),
  error = ref(''),
  lastUpdated = ref<number | null>(null),
  online = ref(navigator.onLine),
  more = ref(false),
  refreshing = ref(false)
const review = ref<Order[]>([]),
  newDefects = ref<Defect[]>([]),
  newParts = ref<Part[]>([]),
  dashboardMore = ref(false)
const failedNotifications = ref<FailedNotification[]>([])
const notificationsMore = ref(false)
const selectedOrder = ref<Order | null>(null),
  selectedVehicle = ref<Vehicle | null>(null),
  selectedDefect = ref<Defect | null>(null),
  composer = ref(false),
  composeVehicle = ref<Vehicle | undefined>(),
  composeDefect = ref<Defect | undefined>(),
  editor = ref<{ kind: Editable; row?: Row; vehicleId?: string } | null>(null)
const settings = ref({
    master_group: 'werkstatt-meister',
    tablet_group: 'werkstatt-tablet',
    admin: false,
  }),
  notice = ref(''),
  privacyKind = ref('customers'),
  privacyId = ref(''),
  eraseId = ref(''),
  eraseConfirmation = ref('')
const vehicleCache = ref<Record<string, Vehicle>>({})
const vehicleById = (id: string) =>
  vehicleCache.value[id] ?? vehicles.value.find((v) => v.id === id)
const navigation = computed(() =>
  master
    ? [
        ['dashboard', t('Übersicht')],
        ['appointments', t('Terminbuch')],
        ['services', t('Leistungskatalog')],
        ['pricing', t('Preise kalkulieren')],
        ['statistics', t('Auswertung')],
        ['orders', t('Aufträge')],
        ['vehicles', t('Fahrzeuge')],
        ['deadlines', t('Termine & Fristen')],
        ['defects', t('Mängel')],
        ['parts', t('Teile')],
        ['library', t('Stammdaten & Vorlagen')],
        ['settings', t('Einstellungen')],
      ]
    : [['orders', t('Werkstattaufträge')]],
)
const heading = computed(
  () => navigation.value.find((x) => x[0] === page.value)?.[1] ?? t('Werkstatt'),
)
const pageHelp = computed(
  () =>
    ({
      statistics: [t('Umsatz und Werkstatt im Blick'), t('Zeitraum wählen, fehlende Auftragsbeträge erfassen und Arbeitszeiten sowie Auslastung vergleichen.')],
      appointments: [t('Termine einfach annehmen'), t('Leistungen auswählen, Arbeitszeit prüfen und einen freien Tag wählen. Aus einem bestätigten Termin kann ein Arbeitsauftrag entstehen.')],
      services: [t('Alle Werkstattleistungen an einem Ort'), t('Leistungen, eigene Zeiten, Pakete und Vergleichspreise für Terminannahme und Auftragserstellung pflegen.')],
      pricing: [t('Preise nachvollziehbar kalkulieren'), t('Jahreskosten eintragen, abrechenbare Stunden festlegen und Leistungen auswählen. Preise erst nach eigener Prüfung in den Katalog übernehmen.')],
      dashboard: [
        t('Was steht heute an?'),
        t(
          'Die vier Felder zeigen, wo etwas zu tun ist. Zum Bearbeiten auf ein Feld klicken. Einen Auftrag legen Sie mit „Neuer Auftrag“ an.',
        ),
      ],
      orders: master
        ? [
            t('Aufträge planen und verfolgen'),
            t(
              'Wählen Sie einen Status und öffnen Sie eine Auftragskarte. „Zur Abnahme“ bedeutet: Die Arbeit ist fertig und wartet auf Ihre Prüfung.',
            ),
          ]
        : [
            t('So beginnen Sie Ihre Arbeit'),
            t(
              '1. Auftragskarte öffnen. 2. Eigenen Namen auswählen. 3. „Arbeit starten“ drücken. Zum Pausieren oder Fertigmelden den Auftrag unter „In Arbeit“ wieder öffnen.',
            ),
          ],
      vehicles: [
        t('Alle Informationen zu einem Fahrzeug'),
        t(
          'Kennzeichen in die Suche eingeben und die Fahrzeugkarte öffnen. Dort finden Sie Aufträge, Mängel, Termine und Dokumente. Ein neues Fahrzeug legen Sie mit „Fahrzeug hinzufügen“ an.',
        ),
      ],
      deadlines: [
        t('Keine Prüfung vergessen'),
        t(
          'Öffnen Sie die Fahrzeugakte, um eine Frist einzutragen oder als erledigt zu bestätigen. Bei wiederkehrenden Fristen entsteht danach der nächste Termin.',
        ),
      ],
      defects: [
        t('Meldung lesen, dann entscheiden'),
        t(
          'Mit „Meldung prüfen“ öffnen Sie die Angaben des Fahrers. Anschließend können Sie einen Auftrag erstellen oder die Meldung einem vorhandenen Auftrag zuordnen.',
        ),
      ],
      parts: [
        t('Welche Teile werden gebraucht?'),
        t(
          'Öffnen Sie den zugehörigen Auftrag, um eine Anforderung als bestellt oder eingetroffen zu markieren. Die Bestellung beim Lieferanten erledigen Sie außerhalb dieser App.',
        ),
      ],
      library: [
        t('Einmal vorbereiten, täglich Zeit sparen'),
        t(
          'Hier pflegen Sie Kunden, Mitarbeiternamen, Auftragsvorlagen und Textbausteine. Vorlagen füllen einen Auftrag aus; Textbausteine ergänzen einzelne Arbeitsschritte.',
        ),
      ],
      settings: [
        t('Zugänge und Verwaltung'),
        t(
          'Diese Einstellungen betreffen Rechte und Datenverwaltung. Für die tägliche Werkstattarbeit müssen Sie hier nichts ändern.',
        ),
      ],
    })[page.value] ?? [],
)
const visibleOrders = computed(() =>
  orders.value.filter((o) => !employeeFilter.value || o.last_employee_id === employeeFilter.value),
)
const shownVehicles = computed(() =>
  vehicles.value.filter((v) => !fleetFilter.value || v.kind === fleetFilter.value),
)
const due = computed(() =>
  deadlines.value.filter((d) => deadlineState(d, vehicleById(d.vehicle_id)) !== 'later'),
)
function selectVehicleOrder(order: Order) {
  selectedOrder.value = order
}
function defectSaved() {
  selectedDefect.value = null
  void load()
}
function entitySaved() {
  editor.value = null
  void refresh()
}
function showReview() {
  page.value = 'orders'
  status.value = 'review'
}
let timer: ReturnType<typeof setInterval> | undefined,
  searchTimer: ReturnType<typeof setTimeout> | undefined,
  revision = 0,
  queuedRefresh = false
async function visiblePage<K extends Table>(
  kind: K,
  query: Record<string, string | number>,
  existing: number,
  append: boolean,
): Promise<Page<Tables[K]>> {
  if (append) return list(kind, { ...query, offset: existing })
  const items: Tables[K][] = []
  let more = false
  do {
    const p = await list(kind, { ...query, offset: items.length })
    items.push(...p.items)
    more = p.more
  } while (more && items.length < Math.max(100, existing))
  return { items, more, offset: 0 }
}
async function load(append = false) {
  if (role === 'driver' || role === 'reception') return
  if (refreshing.value) {
    queuedRefresh = true
    return
  }
  refreshing.value = true
  const rev = ++revision
  error.value = ''
  try {
    if (page.value === 'orders') {
      const p = await visiblePage(
        'orders',
        {
          q: search.value,
          ...(status.value ? { status: status.value } : {}),
          ...(employeeFilter.value ? { employee_id: employeeFilter.value } : {}),
        },
        orders.value.length,
        append,
      )
      if (rev !== revision) return
      orders.value = append ? [...orders.value, ...p.items] : p.items
      more.value = p.more
    } else if (page.value === 'vehicles') {
      const p = await visiblePage(
        'vehicles',
        {
          q: search.value,
        },
        vehicles.value.length,
        append,
      )
      if (rev !== revision) return
      vehicles.value = append ? [...vehicles.value, ...p.items] : p.items
      more.value = p.more
    } else if (page.value === 'library') {
      const p = await visiblePage(
        library.value,
        {
          q: search.value,
        },
        records.value.length,
        append,
      )
      if (rev !== revision) return
      records.value = append ? [...records.value, ...p.items] : p.items
      more.value = p.more
    } else if (page.value === 'defects') {
      const p = await visiblePage('defects', {}, defects.value.length, append)
      if (rev !== revision) return
      defects.value = append ? [...defects.value, ...p.items] : p.items
      more.value = p.more
    } else if (page.value === 'parts') {
      const p = await visiblePage('parts', {}, parts.value.length, append)
      if (rev !== revision) return
      parts.value = append ? [...parts.value, ...p.items] : p.items
      more.value = p.more
    } else if (page.value === 'deadlines') {
      const p = await visiblePage('deadlines', {}, deadlines.value.length, append)
      if (rev !== revision) return
      deadlines.value = append ? [...deadlines.value, ...p.items] : p.items
      more.value = p.more
    } else if (page.value === 'dashboard') {
      const [r, d, p, fr, failed] = await Promise.all([
        list('orders', { status: 'review' }),
        list('defects', { status: 'new' }),
        list('parts', { status: 'requested' }),
        list('deadlines', { status: 'open' }),
        list('outbox'),
      ])
      if (rev !== revision) return
      review.value = r.items
      newDefects.value = d.items
      newParts.value = p.items
      deadlines.value = fr.items
      failedNotifications.value = failed.items
      notificationsMore.value = failed.more
      dashboardMore.value = r.more || d.more || p.more || fr.more
      more.value = false
    } else if (page.value === 'settings') {
      settings.value = await get('/api/v1/settings')
      await loadFailedNotifications()
      more.value = false
    }
    active.value = await all('active')
    const related =
      page.value === 'orders'
        ? orders.value
        : page.value === 'deadlines'
          ? deadlines.value
          : page.value === 'defects'
            ? defects.value
            : page.value === 'dashboard'
              ? [...review.value, ...newDefects.value, ...deadlines.value]
              : []
    const ids = [...new Set(related.map((row) => row.vehicle_id))]
    for (let i = 0; i < ids.length; i += 100) {
      const result = await list('vehicles', { ids: ids.slice(i, i + 100).join(',') })
      if (rev !== revision) return
      for (const v of result.items) vehicleCache.value[v.id] = v
    }
    lastUpdated.value = Date.now() / 1000
    online.value = true
  } catch (e) {
    if (isAxiosError(e)) online.value = !!e.response
    error.value = message(e)
  } finally {
    refreshing.value = false
    if (queuedRefresh) {
      queuedRefresh = false
      queueMicrotask(() => void load())
    }
  }
}
async function loadFailedNotifications(append = false) {
  const result = await list('outbox', { offset: append ? failedNotifications.value.length : 0 })
  failedNotifications.value = append
    ? [...failedNotifications.value, ...result.items]
    : result.items
  notificationsMore.value = result.more
}
async function retryNotification(item: FailedNotification) {
  busy.value = true
  error.value = ''
  try {
    const result = await post<{ queued: boolean }>('/api/v1/actions/notification-retry', {
      data: { id: item.id, version: item.version },
    })
    notice.value = result.queued
      ? t('Zustellung erneut vorgemerkt. Der Hintergrundjob übernimmt den Versand.')
      : t('Zustellung verworfen: Der Empfänger hat keine Meisterrechte mehr.')
    await loadFailedNotifications()
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function moreNotifications() {
  busy.value = true
  try {
    await loadFailedNotifications(true)
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function initial() {
  if (role === 'driver' || role === 'reception') return
  busy.value = true
  try {
    ;[employees.value, vehicles.value] = await Promise.all([
      all('employees'),
      list('vehicles').then((x) => x.items),
    ])
    await load()
  } catch (e) {
    if (isAxiosError(e)) online.value = !!e.response
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
function connectivity() {
  // Browser network heuristics cannot determine whether this Nextcloud is reachable.
  void load()
}
onMounted(() => {
  void initial()
  timer = setInterval(() => {
    if (
      page.value !== 'settings' &&
      !document.hidden &&
      !selectedOrder.value &&
      !selectedVehicle.value &&
      !selectedDefect.value &&
      !editor.value &&
      !composer.value
    )
      void load()
  }, 10000)
  window.addEventListener('online', connectivity)
  window.addEventListener('offline', connectivity)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  clearTimeout(searchTimer)
  window.removeEventListener('online', connectivity)
  window.removeEventListener('offline', connectivity)
})
watch([page, library, status, employeeFilter], () => {
  if (page.value === 'pricing') pricingVisited.value = true
  if (page.value === 'statistics') statisticsVisited.value = true
  if (page.value === 'settings') settingsVisited.value = true
  ++revision
  search.value = ''
  more.value = false
  orders.value = []
  records.value = []
  defects.value = []
  parts.value = []
  deadlines.value = []
  void load()
})
watch(search, () => {
  ++revision
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void load(), 250)
})
function create(v?: Vehicle, d?: Defect) {
  selectedDefect.value = null
  composeVehicle.value = v
  composeDefect.value = d
  composer.value = true
}
function edit(kind: Editable, row?: Row, vehicleId?: string) {
  editor.value = { kind, row, vehicleId }
}
async function refresh() {
  await initial()
}
async function orderCreated(o: Order) {
  notice.value =
    o.status === 'draft'
      ? t('Entwurf gespeichert. Der Auftrag ist noch nicht am Tablet sichtbar.')
      : t('Auftrag freigegeben. Die Mitarbeiter können ihn jetzt am Tablet starten.')
  composer.value = false
  selectedOrder.value = o
  await load()
}
async function openOrder(id: string) {
  try {
    let o = orders.value.find((x) => x.id === id) || review.value.find((x) => x.id === id)
    if (!o) o = (await list('orders', { id })).items[0]
    if (o) {
      selectedOrder.value = o
      await ensureVehicle(o.vehicle_id)
    }
  } catch (e) {
    error.value = message(e)
  }
}
async function openAppointmentOrder(o: Order) { await ensureVehicle(o.vehicle_id); selectedOrder.value = o }
async function ensureVehicle(id: string) {
  if (!vehicleById(id)) {
    const v = (await list('vehicles', { id })).items[0]
    if (v) vehicles.value.push(v)
  }
}
async function openDefect(d: Defect) {
  try {
    await ensureVehicle(d.vehicle_id)
    selectedDefect.value = d
  } catch (e) {
    error.value = message(e)
  }
}
async function openVehicle(id: string) {
  try {
    await ensureVehicle(id)
    selectedVehicle.value = vehicleById(id) ?? null
  } catch (e) {
    error.value = message(e)
  }
}
function rowTitle(r: Row): string {
  return 'title' in r ? r.title : 'name' in r ? r.name : 'plate' in r ? vehicleLabel(r) : r.id
}
async function exportCsv(kind: Table) {
  busy.value = true
  try {
    const axios = (await import('@nextcloud/axios')).default
    const r = await axios.get(url(`/api/v1/export/${kind}`), { responseType: 'blob' })
    const href = URL.createObjectURL(r.data),
      a = document.createElement('a')
    a.href = href
    a.download = `werkstatt-${kind}.csv`
    a.click()
    setTimeout(() => URL.revokeObjectURL(href), 1000)
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function anonymize() {
  busy.value = true
  try {
    await post('/api/v1/actions/anonymize', {
      data: { kind: privacyKind.value, id: privacyId.value },
    })
    notice.value = t('Stammdaten anonymisiert. Freitexte und Anhänge separat prüfen.')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
async function erase() {
  busy.value = true
  try {
    await post('/api/v1/actions/erase-vehicle', {
      data: { id: eraseId.value, confirmation: eraseConfirmation.value },
    })
    notice.value = t(
      'Archiviertes Fahrzeug und zugehörige Fachdatensätze gelöscht. Dateien werden durch den Bereinigungsjob entfernt.',
    )
    eraseId.value = ''
    eraseConfirmation.value = ''
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <DriverForm v-if="role === 'driver'" />
  <main v-else-if="role === 'reception'" class="wm-app wm-reception"><CompanyHeader /><h1>{{ t('Terminannahme') }}</h1><p class="wm-guide">{{ t('Kundentermine vereinbaren, ändern oder absagen. Arbeitsaufträge und Werkstattverwaltung übernimmt der Meister.') }}</p><AppointmentBook :reception="true" /></main>
  <div v-else class="wm-shell" :class="{ 'wm-tablet': !master, 'wm-report-page': page === 'statistics' }">
    <aside class="wm-sidebar">
      <div class="wm-brand">
        <span class="wm-brand-icon">W</span>
        <div>
          <strong>{{ t('Werkstattmanager') }}</strong><small>{{
            master ? t('Planen. Arbeiten. Dokumentieren.') : t('Werkstatt-Tablet')
          }}</small>
        </div>
      </div>
      <nav :aria-label="t('Werkstattnavigation')">
        <button
          v-for="[key, label] in navigation"
          :key="key"
          :class="{ selected: page === key }"
          :aria-current="page === key ? 'page' : undefined"
          @click="page = key"
        >
          {{ label }}
        </button>
      </nav>
      <div class="wm-sidebar-note">
        <span :class="online ? 'wm-dot' : 'wm-dot-off'"></span>{{ online ? t('Verbunden') : t('Offline')
        }}<small>{{
          t(
            'Nach jeder Aktion auf die Bestätigung warten. Danach kann der nächste Mitarbeiter das Tablet benutzen.',
          )
        }}</small>
      </div>
    </aside>
    <main class="wm-main">
      <header class="wm-top">
        <div>
<CompanyHeader />
          <span class="wm-eyebrow">{{
            master ? t('MEISTERBEREICH') : t('GEMEINSAM ARBEITEN')
          }}</span>
          <h1>{{ heading }}</h1>
        </div>
        <NcButton v-if="master" variant="primary" :disabled="busy || !online" @click="create()">
          + {{ t('Neuer Auftrag') }}
        </NcButton>
      </header>
      <section class="wm-guide" :aria-label="t('Hilfe zu dieser Ansicht')">
        <strong>{{ pageHelp[0] }}</strong>
        <p>{{ pageHelp[1] }}</p>
      </section>
      <p v-if="!online" role="alert" class="wm-error">
        {{ t('Keine Verbindung. Buchungen sind erst nach Wiederherstellung möglich.') }}
      </p>
      <p v-if="error" role="alert" class="wm-error">
        {{ error }} <NcButton @click="refresh">{{ t('Erneut versuchen') }}</NcButton>
      </p>
      <p v-if="notice" role="status" class="wm-success">{{ notice }}</p>
      <p v-if="busy" role="status">{{ t('Wird geladen …') }}</p>
      <AppointmentBook v-if="master && page === 'appointments'" @order="openAppointmentOrder" />
      <WorkshopStatistics v-if="master && (page === 'statistics' || statisticsVisited)" v-show="page === 'statistics'" :active="page === 'statistics'" @order="openOrder" @appointments="page = 'appointments'" />
      <ServiceCatalogue v-if="master && page === 'services'" />
      <CustomerMailSettings v-if="master && (page === 'settings' || settingsVisited)" v-show="page === 'settings'" />
      <PriceCalculator v-if="master && (page === 'pricing' || pricingVisited)" v-show="page === 'pricing'" />
      <template v-if="page === 'dashboard'">
        <p v-if="failedNotifications.length" role="status" class="wm-error">
          {{ t('Benachrichtigungen konnten nicht zugestellt werden.') }}
          <NcButton @click="page = 'settings'">{{ t('Zustellungen prüfen') }}</NcButton>
        </p>
        <div class="wm-stats">
          <button @click="page = 'defects'">
            <span>{{ newDefects.length }}</span>{{ t('Neue Mängel') }}
</button><button @click="page = 'deadlines'">
            <span>{{ due.length }}</span>{{ t('Anstehende Fristen') }}
</button><button @click="page = 'parts'">
            <span>{{ newParts.length }}</span>{{ t('Teileanforderungen') }}
</button><button @click="showReview">
            <span>{{ review.length }}</span>{{ t('Zur Abnahme') }}
          </button>
        </div>
        <p v-if="dashboardMore" class="wm-muted">
          {{
            t(
              'Die Übersicht zeigt bis zu 100 Einträge je Bereich. Weitere Einträge finden Sie in den jeweiligen Listen.',
            )
          }}
        </p>
        <section class="wm-card">
          <h2>{{ t('Wer arbeitet woran?') }}</h2>
          <div class="wm-team">
            <div v-for="e in employees.filter((e) => !e.archived)" :key="e.id" class="wm-person">
              <span class="wm-avatar">{{ e.name.slice(0, 2).toUpperCase() }}</span>
              <div>
                <strong>{{ e.name }}</strong><NcButton
                  v-if="active.find((a) => a.employee_id === e.id)"
                  @click="openOrder(active.find((a) => a.employee_id === e.id)!.order_id)"
                >
                  {{ t('Aktiven Auftrag öffnen') }}
</NcButton><small v-else>{{ t('Frei') }}</small>
              </div>
            </div>
            <p v-if="!employees.length">
              {{ t('Legen Sie unter Stammdaten Ihre Mitarbeiter an.') }}
            </p>
          </div>
        </section>
        <div class="wm-two">
          <section class="wm-card">
            <h2>{{ t('Zur Abnahme bereit') }}</h2>
            <button v-for="o in review" :key="o.id" class="wm-list-button" @click="openOrder(o.id)">
              <strong>{{ o.title }}</strong><span>{{ vehicleLabel(vehicleById(o.vehicle_id)) }}</span>
            </button>
            <p v-if="!review.length" class="wm-muted">
              {{ t('Aktuell wartet kein Auftrag auf Abnahme.') }}
            </p>
          </section>
          <section class="wm-card">
            <h2>{{ t('Termine im Blick') }}</h2>
            <button
              v-for="d in due"
              :key="d.id"
              class="wm-list-button"
              @click="openVehicle(d.vehicle_id)"
            >
              <strong>{{ d.title }}</strong><span>{{ vehicleLabel(vehicleById(d.vehicle_id)) }} · {{ date(d.due_date) }}
                <template v-if="d.due_km !== null"> / {{ d.due_km }} km</template></span>
            </button>
            <p v-if="!due.length" class="wm-muted">
              {{ t('Keine anstehenden Fristen in dieser Übersicht.') }}
            </p>
          </section>
        </div>
      </template>
      <template v-if="page === 'orders'">
        <div class="wm-toolbar">
          <label>{{ t('Suche')
            }}<input
              v-model="search"
              type="search"
              :placeholder="t('Auftragstitel oder Beschreibung')"
/></label><label>{{ t('Mitarbeiter')
            }}<select v-model="employeeFilter">
              <option value="">{{ t('Alle') }}</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select></label>
        </div>
        <nav class="wm-tabs" :aria-label="t('Auftragsstatus')">
          <NcButton v-if="master" :aria-pressed="status === ''" @click="status = ''">
            {{ t('Alle') }}
</NcButton><NcButton
            v-for="s in master
              ? ['draft', 'open', 'active', 'paused', 'review', 'completed', 'cancelled']
              : ['open', 'active', 'paused', 'review']"
            :key="s"
            :aria-pressed="status === s"
            @click="status = s"
          >
            {{ statusText(s) }}
          </NcButton>
        </nav>
        <div class="wm-grid">
          <button
            v-for="o in visibleOrders"
            :key="o.id"
            class="wm-order-card"
            :class="{ 'wm-urgent': o.priority === 'urgent' }"
            @click="openOrder(o.id)"
          >
            <div>
              <span class="wm-badge" :data-status="o.status">{{ statusText(o.status) }}</span><small>{{ orderNumber(o.id) }}</small>
            </div>
            <h2>{{ o.title }}</h2>
            <strong>{{ vehicleLabel(vehicleById(o.vehicle_id)) }}</strong>
            <p>{{ o.description.slice(0, 150) }}</p>
            <span class="wm-card-link">{{ t('Auftrag öffnen →') }}</span>
            <footer>
              <span>{{
                o.minutes ? o.minutes + ' min ' + t('Sollzeit') : t('Ohne Zeitvorgabe')
              }}</span><span>{{
                o.due_at ? t('Fertig bis') + ' ' + stamp(o.due_at) : t('Ohne festen Termin')
              }}</span>
            </footer>
          </button>
        </div>
        <p v-if="!visibleOrders.length && !busy" class="wm-empty">
          {{
            t(
              'Hier sind keine passenden Aufträge. Wählen Sie einen anderen Status oder löschen Sie den Suchtext.',
            )
          }}
        </p>
      </template>
      <template v-if="page === 'vehicles'">
        <div class="wm-toolbar">
          <label>{{ t('Fahrzeug suchen')
            }}<input
              v-model="search"
              type="search"
              :placeholder="t('Kennzeichen, FIN, Nummer oder Kunde')"
/></label><label>{{ t('Fahrzeugart')
            }}<select v-model="fleetFilter">
              <option value="">{{ t('Alle') }}</option>
              <option value="fleet">{{ t('Eigene Flotte') }}</option>
              <option value="customer">{{ t('Kundenfahrzeuge') }}</option>
            </select></label><NcButton @click="edit('vehicles')">+ {{ t('Fahrzeug hinzufügen') }}</NcButton>
        </div>
        <div class="wm-grid">
          <button
            v-for="v in shownVehicles"
            :key="v.id"
            class="wm-order-card"
            @click="selectedVehicle = v"
          >
            <span class="wm-plate">{{ v.plate || v.number }}</span>
            <h2>{{ [v.make, v.model].join(' ') }}</h2>
            <p>
              {{ v.kind === 'fleet' ? t('Eigene Flotte') : t('Kundenfahrzeug') }}
              {{ v.archived ? ' · ' + t('Archiviert') : '' }}
            </p>
            <footer>
              <span>{{ v.mileage ?? '–' }} km</span><span>{{ date(v.mileage_date) }}</span>
            </footer>
          </button>
        </div>
        <p v-if="!shownVehicles.length" class="wm-empty">
          {{ t('Noch keine passenden Fahrzeuge.') }}
        </p>
      </template>
      <template v-if="page === 'defects'">
        <section class="wm-card">
          <article v-for="d in defects" :key="d.id" class="wm-log">
            <div class="wm-actions">
              <span class="wm-badge" :data-status="d.status">{{ statusText(d.status) }}</span><strong>{{ vehicleLabel(vehicleById(d.vehicle_id)) }}</strong>
            </div>
            <p>{{ d.description }}</p>
            <small>{{ stamp(d.created_at) }}</small><NcButton @click="openDefect(d)">{{ t('Meldung prüfen') }}</NcButton>
          </article>
          <p v-if="!defects.length">{{ t('Keine Mängelmeldungen vorhanden.') }}</p>
        </section>
      </template>
      <template v-if="page === 'parts'">
        <section class="wm-card">
          <article v-for="p in parts" :key="p.id" class="wm-log">
            <strong>{{ p.quantity }} × {{ p.title }}</strong><span class="wm-badge">{{ statusText(p.status) }}</span>
            <p>{{ p.part_number }} · {{ p.note }}</p>
            <NcButton @click="openOrder(p.order_id)">
              {{ t('Auftrag und Teileanforderung öffnen') }}
            </NcButton>
          </article>
          <p v-if="!parts.length">{{ t('Keine Teileanforderungen vorhanden.') }}</p>
        </section>
      </template>
      <template v-if="page === 'deadlines'">
        <div class="wm-actions">
          <NcButton @click="edit('deadlines')">+ {{ t('Termin / Frist') }}</NcButton>
        </div>
        <section class="wm-card">
          <article v-for="d in deadlines" :key="d.id" class="wm-log">
            <span class="wm-badge" :data-status="deadlineState(d, vehicleById(d.vehicle_id))">{{
              statusText(d.status)
            }}</span><strong>{{ d.title }}</strong>
            <p>
              {{ vehicleLabel(vehicleById(d.vehicle_id)) }} · {{ date(d.due_date) }}
              <span v-if="d.due_km !== null"> / {{ d.due_km }} km</span>
            </p>
            <small>{{ t('Letzter Kilometerstand') }}: {{ vehicleById(d.vehicle_id)?.mileage ?? '–' }} ·
              {{ date(vehicleById(d.vehicle_id)?.mileage_date) }}</small>
            <div class="wm-actions">
              <NcButton @click="edit('deadlines', d)">{{ t('Bearbeiten') }}</NcButton><NcButton @click="openVehicle(d.vehicle_id)">
                {{ t('Fahrzeugakte / Erledigen') }}
              </NcButton>
            </div>
          </article>
          <p v-if="!deadlines.length">{{ t('Noch keine Termine oder Fristen.') }}</p>
        </section>
      </template>
      <template v-if="page === 'library'">
        <CompanySettings />
        <div class="wm-toolbar">
          <label>{{ t('Bereich')
            }}<select v-model="library">
              <option value="templates">{{ t('Auftragsvorlagen') }}</option>
              <option value="snippets">{{ t('Textbausteine') }}</option>
              <option value="customers">{{ t('Kunden / Firmen') }}</option>
              <option value="employees">{{ t('Mitarbeiter') }}</option>
            </select></label><label>{{ t('Suche') }}<input v-model="search" type="search" /></label><NcButton variant="primary" @click="edit(library)">
            + {{ t('Eintrag anlegen') }}
          </NcButton>
        </div>
        <section class="wm-card">
          <article v-for="r in records" :key="r.id" class="wm-log">
            <strong>{{ rowTitle(r) }}</strong><span v-if="'archived' in r && r.archived" class="wm-badge">{{ t('Archiviert') }}</span><span v-if="'favorite' in r && r.favorite"> ★</span>
            <p v-if="'description' in r">{{ r.description }}</p>
            <p v-if="'text' in r">{{ r.text }}</p>
            <NcButton @click="edit(library, r)">{{ t('Bearbeiten') }}</NcButton>
          </article>
          <p v-if="!records.length">
            {{ t('Hier können Sie Ihre Stammdaten und Schnellvorlagen pflegen.') }}
          </p>
        </section>
      </template>
      <template v-if="page === 'settings'">
        <MailSettings :admin="settings.admin" />
        <CalendarSettings :admin="settings.admin" />
        <section class="wm-card">
          <h2>{{ t('Fehlgeschlagene Benachrichtigungen') }}</h2>
          <p>
            {{
              t(
                'Nach Behebung der Ursache können Sie die Zustellung erneut vormerken. Die eigentlichen Aufträge und Meldungen bleiben erhalten.',
              )
            }}
          </p>
          <article v-for="item in failedNotifications" :key="item.id" class="wm-log">
            <strong>{{ item.recipient }}</strong>
            <p>
              {{ stamp(item.created_at) }} ·
              {{ t('Zustellversuche: {count}', { count: item.attempts }) }}
            </p>
            <NcButton :disabled="busy" @click="retryNotification(item)">
{{
              t('Erneut zustellen')
            }}
</NcButton>
          </article>
          <p v-if="!failedNotifications.length">{{ t('Keine fehlgeschlagenen Zustellungen.') }}</p>
          <NcButton v-if="notificationsMore" :disabled="busy" @click="moreNotifications">
{{
            t('Weitere laden')
          }}
</NcButton>
        </section>
        <AccessSettings :admin="settings.admin" />
        <section class="wm-card">
          <h2>{{ t('Daten exportieren') }}</h2>
          <div class="wm-chips">
            <NcButton
              v-for="kind in [
                'customers',
                'vehicles',
                'orders',
                'intervals',
                'parts',
                'defects',
                'deadlines',
                'mileage',
                'audit',
              ] as Table[]"
              :key="kind"
              :disabled="busy"
              @click="exportCsv(kind)"
            >
              {{ kind }} CSV
            </NcButton>
          </div>
        </section>
        <section v-if="settings.admin" class="wm-card wm-form">
          <h2>{{ t('Datenschutz und Löschung') }}</h2>
          <p>
            {{
              t(
                'Vor Löschung Daten exportieren und Aufbewahrungsregeln prüfen. Freitexte und Anhänge können personenbezogene Angaben enthalten.',
              )
            }}
          </p>
          <label>{{ t('Stammdaten anonymisieren')
            }}<select v-model="privacyKind">
              <option value="customers">{{ t('Archivierter Kunde') }}</option>
              <option value="employees">{{ t('Archivierter Mitarbeiter') }}</option>
              <option value="defects">{{ t('Name eines Meldenden') }}</option>
            </select></label><label>{{ t('Datensatz-ID aus Export') }}<input v-model="privacyId" /></label><NcButton :disabled="busy || !privacyId" @click="anonymize">
            {{ t('Anonymisieren') }}
          </NcButton>
          <hr />
          <label>{{ t('Archiviertes Fahrzeug endgültig löschen – Fahrzeug-ID')
            }}<input v-model="eraseId" /></label><label>{{ t('Zur Bestätigung dieselbe Fahrzeug-ID erneut eingeben')
            }}<input v-model="eraseConfirmation" /></label><NcButton :disabled="busy || !eraseId || eraseId !== eraseConfirmation" @click="erase">
            {{ t('Fahrzeug und zugehörige Daten endgültig löschen') }}
          </NcButton>
        </section>
      </template>
      <NcButton v-if="more" :disabled="refreshing" @click="load(true)">
        {{ t('Weitere laden') }}
      </NcButton>
      <footer class="wm-page-footer">
        {{ t('Letzte Aktualisierung') }}: {{ stamp(lastUpdated)
        }}<span v-if="refreshing"> · {{ t('Aktualisierung …') }}</span><NcButton :disabled="refreshing" @click="refresh">{{ t('Aktualisieren') }}</NcButton>
      </footer>
    </main>
    <OrderComposer
      v-if="composer"
      :close-label="selectedVehicle ? t('Zurück zur Fahrzeugakte') : undefined"
      :vehicle="composeVehicle"
      :defect-id="composeDefect?.id"
      :initial-description="composeDefect?.description"
      @close="composer = false"
      @created="orderCreated"
    />
    <OrderDetail
      v-if="selectedOrder"
      :key="selectedOrder.id"
      :close-label="selectedVehicle ? t('Zurück zur Fahrzeugakte') : undefined"
      :order="selectedOrder"
      :vehicle="vehicleById(selectedOrder.vehicle_id)"
      :master="master"
      :employees="employees"
      @close="selectedOrder = null"
      @changed="load()"
    />
    <VehicleDetail
      v-if="selectedVehicle"
      :vehicle="selectedVehicle"
      :active="!selectedOrder && !selectedDefect && !editor && !composer"
      @close="selectedVehicle = null"
      @edit="edit('vehicles', $event)"
      @create="create(selectedVehicle ?? undefined)"
      @order="selectVehicleOrder"
      @defect="openDefect"
      @deadline="edit('deadlines', undefined, selectedVehicle?.id)"
      @changed="load()"
    />
    <DefectDetail
      v-if="selectedDefect"
      :close-label="selectedVehicle ? t('Zurück zur Fahrzeugakte') : undefined"
      :defect="selectedDefect"
      :vehicle="vehicleById(selectedDefect.vehicle_id)"
      @close="selectedDefect = null"
      @saved="defectSaved"
      @create="create(vehicleById(selectedDefect!.vehicle_id), selectedDefect!)"
    />
    <EntityEditor
      v-if="editor"
      :key="editor.kind + (editor.row?.id ?? '')"
      :close-label="selectedVehicle ? t('Zurück zur Fahrzeugakte') : undefined"
      :kind="editor.kind"
      :row="editor.row"
      :vehicle-id="editor.vehicleId"
      @close="editor = null"
      @saved="entitySaved"
    />
  </div>
</template>
