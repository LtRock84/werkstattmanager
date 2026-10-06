import { t } from './format'
import type { ServiceDefinition, ServiceLine, PriceGroup } from './types'
export function comparisonPrice(group: PriceGroup): ServiceLine['comparison'] | null {
  const rates = [...new Set(group.observations.map(o => o.vat))]
  if (group.count < 5 || group.mean == null || rates.length !== 1 || rates[0] == null || rates[0] < 0 || rates[0] > 100) return null
  return { net_cents: Math.round(group.mean * 100 / (100 + rates[0])), gross_cents: group.mean, vat: rates[0], scope: group.scope, material: group.material, unit: group.unit, region: group.region, price_kind: group.price_kind }
}
export const money = (cents: number | null | undefined) => cents == null ? '–' : new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100)
export function serviceLine(s: ServiceDefinition, vehicleClass: 'car' | 'van' = 'car'): ServiceLine {
  return { service_id: s.id, key: s.seed_key, title: s.title, quantity: 1, minutes: vehicleClass === 'van' && s.time_kind === 'research' ? null : s.minutes, unit: s.unit, steps: [...s.steps], vehicle_class: vehicleClass }
}
export function lineMinutes(lines: ServiceLine[]): number | null {
  return lines.some(l => l.minutes == null) ? null : lines.reduce((total, l) => total + (l.minutes ?? 0) * l.quantity, 0)
}
export function includedWork(lines: ServiceLine[], definitions: ServiceDefinition[]): string[] {
  const selected = new Set(lines.map(l => l.key))
  return definitions.filter(s => selected.has(s.seed_key)).flatMap(s => s.includes.filter(k => selected.has(k)).map(k => `${s.title} → ${definitions.find(d => d.seed_key === k)?.title ?? k}`))
}

// Appointment inputs always use the workshop zone, regardless of the device zone.
export function berlinInput(value?: string | null): string {
  if (!value) return ''
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Berlin', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(value))
  const part = (name: string) => parts.find(p => p.type === name)?.value ?? ''
  return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`
}
export function berlinIso(local: string): string | null {
  if (!local) return null
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(local)) throw new Error(t('Bitte Datum und Uhrzeit vollständig angeben.'))
  const candidates = [60, 120].map(offset => new Date(Date.parse(local + 'Z') - offset * 60000)).filter(d => Number.isFinite(d.getTime()) && berlinInput(d.toISOString()) === local)
  if (candidates.length !== 1) throw new Error(candidates.length ? t('Diese Uhrzeit kommt bei der Zeitumstellung zweimal vor. Bitte eine eindeutige Uhrzeit außerhalb 02:00–03:00 wählen.') : t('Diese Uhrzeit existiert bei der Zeitumstellung nicht. Bitte eine andere Uhrzeit wählen.'))
  return candidates[0].toISOString()
}
