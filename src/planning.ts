import type { Appointment, CapacityDay, Vehicle, ReceptionChecklist } from './types'
import { berlinInput } from './services'
import { t } from './format'

const isoDay = (date: Date) => date.toISOString().slice(0, 10)
const calendarDate = (day: string) => new Date(day + 'T12:00:00Z')
export function shiftBookDay(day: string, mode: string, direction: number): string {
  const date = calendarDate(day)
  if (!Number.isFinite(date.getTime()) || isoDay(date) !== day) return day
  if (mode === 'month') { date.setUTCDate(1); date.setUTCMonth(date.getUTCMonth() + direction) }
  else date.setUTCDate(date.getUTCDate() + direction * (mode === 'week' ? 7 : 1))
  return isoDay(date)
}
export function bookRange(day: string, mode: string): string[] {
  const from = calendarDate(day), to = calendarDate(day)
  if (!Number.isFinite(from.getTime()) || isoDay(from) !== day) return []
  if (mode === 'week') { from.setUTCDate(from.getUTCDate() - (from.getUTCDay() + 6) % 7); to.setTime(from.getTime()); to.setUTCDate(to.getUTCDate() + 6) }
  if (mode === 'month') { from.setUTCDate(1); to.setUTCMonth(to.getUTCMonth() + 1, 0) }
  return [isoDay(from), isoDay(to)]
}
export function appointmentMinutesOn(a: Appointment, day: string): number | null {
  if (a.allocations.length) return a.allocations.filter(d => d.date === day).reduce((sum, d) => sum + d.minutes, 0)
  return berlinInput(a.work_start ?? a.arrival).slice(0, 10) === day ? a.minutes : 0
}

export function emptyChecklist(): ReceptionChecklist {
  return { key_received: false, documents_checked: false, damage_checked: false, work_agreed: false, final_checked: false, customer_informed: false, key_returned: false, mileage: null, condition_note: '', callback_note: '' }
}
export function receptionLabels(): Record<keyof ReceptionChecklist, string> {
  return { key_received: t('Fahrzeugschlüssel entgegengenommen'), documents_checked: t('Benötigte Unterlagen geprüft'), damage_checked: t('Vorhandene Schäden mit dem Kunden besprochen'), work_agreed: t('Arbeitsumfang mit dem Kunden abgesprochen'), final_checked: t('Abschlusskontrolle durchgeführt'), customer_informed: t('Kunden über Fertigstellung informiert'), key_returned: t('Schlüssel an den Kunden zurückgegeben'), mileage: t('Kilometerstand bei Annahme'), condition_note: t('Vorhandene Schäden / übergebenes Zubehör'), callback_note: t('Rückfrage / Rückrufnotiz') }
}

/** Only copy planned work, never history, contact snapshots or earlier commitments. */
export function followUpWork(a: Appointment) {
  return { vehicle_id: a.vehicle_id, title: a.title, minutes: a.minutes, service_lines: JSON.parse(JSON.stringify(a.service_lines)) as Appointment['service_lines'] }
}

export function freePlanningDays(days: CapacityDay[], minutes: number | null): CapacityDay[] {
  if (!minutes || !Number.isFinite(minutes) || minutes < 1) return []
  return days.filter(d => !d.closed && !d.overbooked && d.free >= minutes && (d.max_vehicles == null || d.vehicle_count < d.max_vehicles))
}

export function matchesAppointment(a: Appointment, vehicle: Vehicle | undefined, query: string, filter: string): boolean {
  if (filter === 'waiting' && (a.status !== 'confirmed' || !['parts_missing', 'customer_question'].includes(a.stage))) return false
  if (filter === 'ready' && (a.status !== 'confirmed' || a.stage !== 'ready')) return false
  if (['confirmed', 'draft', 'cancelled'].includes(filter) && a.status !== filter) return false
  const normalize = (text: string) => text.normalize('NFKD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('de-DE')
  const text = normalize([a.title, a.customer_email, vehicle?.plate, vehicle?.number, vehicle?.vin, vehicle?.make, vehicle?.model].filter(Boolean).join(' '))
  return normalize(query).trim().split(/\s+/).every(word => text.includes(word))
}
