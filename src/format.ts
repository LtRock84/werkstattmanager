import { translate } from '@nextcloud/l10n'
import type { Deadline, Vehicle, Interval } from './types'
export const t = (text: string, vars?: Record<string, string | number>) =>
  translate('werkstattmanager', text, vars)
export const stamp = (value: number | string | null | undefined) =>
  value
    ? new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: 'Europe/Berlin',
      }).format(new Date(typeof value === 'number' ? value * 1000 : value))
    : '–'
export const date = (value: string | null | undefined) =>
  value ? value.split('-').reverse().join('.') : '–'
export const vehicleLabel = (v?: Partial<Vehicle>) =>
  v ? [v.plate || v.number, v.make, v.model].filter(Boolean).join(' · ') : t('Fahrzeug')
export const orderNumber = (id: string) => `WM-${id.slice(0, 8).toUpperCase()}`
export const duration = (seconds: number) =>
  `${Math.floor(seconds / 3600)}:${String(Math.floor((seconds % 3600) / 60)).padStart(2, '0')} h`
export const total = (rows: Interval[], now = Date.now() / 1000) =>
  rows.reduce((s, r) => s + Math.max(0, (r.ended ?? now) - r.started), 0)
export const iso = (local: string) => (local ? new Date(local).toISOString() : null)
export function localInput(value: number | string | null): string {
  if (value === null || value === '') return ''
  const instant = new Date(typeof value === 'number' ? value * 1000 : value)
  return new Date(instant.getTime() - instant.getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, 16)
}
export function deadlineState(
  d: Deadline,
  v?: Vehicle,
  today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Berlin' }).format(new Date()),
): 'due' | 'soon' | 'later' {
  if (d.status !== 'open') return 'later'
  if (
    (d.due_date && d.due_date <= today) ||
    (d.due_km !== null && v?.mileage !== null && v?.mileage !== undefined && v.mileage >= d.due_km)
  )
    return 'due'
  const days = d.due_date ? (Date.parse(d.due_date) - Date.parse(today)) / 86400000 : Infinity
  return days <= Math.max(0, ...d.warning_days) ? 'soon' : 'later'
}
export function statusText(status: string): string {
  const labels: Record<string, string> = {
    draft: t('Entwurf'),
    open: t('Offen'),
    active: t('In Arbeit'),
    paused: t('Pausiert'),
    review: t('Zur Abnahme'),
    completed: t('Abgeschlossen'),
    cancelled: t('Storniert'),
    requested: t('Angefordert'),
    ordered: t('Bestellt'),
    received: t('Eingetroffen'),
    rejected: t('Abgelehnt'),
    new: t('Ungeprüft'),
    resolved: t('Behoben'),
    duplicate: t('Doppelt'),
    dismissed: t('Verworfen'),
  }
  return labels[status] || status
}
