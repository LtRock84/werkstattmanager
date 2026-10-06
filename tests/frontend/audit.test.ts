import { describe, it, expect, vi } from 'vitest'
vi.mock('@nextcloud/l10n', () => ({
  translate: (_app: string, s: string, values?: Record<string, string>) =>
    s.replace('{account}', values?.account ?? ''),
}))
import { auditEntry } from '../../src/audit'
import type { Audit } from '../../src/types'

const entry = (data: Partial<Audit>): Audit => ({
  id: 'a1',
  version: 1,
  created_at: 1000,
  updated_at: 1000,
  actor: 'master',
  employee_id: null,
  order_id: null,
  action: 'order.start',
  details: {},
  ...data,
})
describe('readable vehicle history', () => {
  it('explains requested parts and paused time with concrete context', () => {
    const result = auditEntry(
      entry({
        action: 'part.requested',
        details: { part_id: 'p1', paused: true },
        context: {
          order: { id: 'a1111111111111111111111111111111', title: 'Inspektion' },
          employee: { name: 'Alex' },
          part: {
            title: 'Ölfilter',
            quantity: 2,
            part_number: 'FILTER-1',
            priority: 'urgent',
            note: 'Vor der Probefahrt',
          },
          defect: null,
          deadline: null,
        },
      }),
    )
    expect(result.title).toBe('Teil für den Auftrag angefordert')
    expect(result.lines).toEqual(
      expect.arrayContaining([
        { label: 'Teil / Menge', value: '2 × Ölfilter' },
        { label: 'Mitarbeiter', value: 'Alex' },
        { label: 'Teilenummer', value: 'FILTER-1' },
        { label: 'Dringlichkeit', value: 'Dringend' },
      ]),
    )
    expect(result.lines.find((line) => line.label === 'Arbeitsablauf')?.value).toContain(
      'Zeiterfassung wurde angehalten',
    )
    expect(JSON.stringify(result)).not.toContain('part_id')
  })
  it('shows work notes and completed steps without internal JSON', () => {
    const result = auditEntry(
      entry({
        action: 'order.finish',
        details: { note: 'Alles geprüft', steps: ['Öl gewechselt', 'Probefahrt'], reason: 'Pause' },
      }),
    )
    expect(result.lines).toEqual([
      { label: 'Arbeitsnotiz', value: 'Alles geprüft' },
      { label: 'Erledigte Arbeiten', value: 'Öl gewechselt\nProbefahrt' },
    ])
  })
  it('handles old events and deleted references without exposing identifiers', () => {
    const result = auditEntry(
      entry({ action: 'part.received', employee_id: 'deleted', details: { part_id: 'secret-id' } }),
    )
    expect(result.lines).toContainEqual({
      label: 'Mitarbeiter',
      value: 'Name nicht mehr verfügbar',
    })
    expect(result.lines).toContainEqual({
      label: 'Teileanforderung',
      value: 'Angaben nicht mehr verfügbar',
    })
    expect(JSON.stringify(result)).not.toContain('secret-id')
    expect(auditEntry(entry({ action: 'future.event', details: { secret: 'hidden' } })).title).toBe(
      'Eintrag im Fahrzeugprotokoll',
    )
  })
  it('does not mislabel old default pause reasons as start or approval reasons', () => {
    for (const action of ['order.start', 'order.approve'])
      expect(auditEntry(entry({ action, details: { reason: 'Pause' } })).lines).toEqual([])
  })
  it('shows appointment arrival as Berlin date and time across DST and day boundaries', () => {
    for (const [arrival, expected] of [
      ['2026-10-12T06:00:00Z', '12.10.26, 08:00'],
      ['2026-10-25T00:30:00Z', '25.10.26, 02:30'],
      ['2026-10-25T01:30:00Z', '25.10.26, 02:30'],
      ['2026-12-31T23:30:00Z', '01.01.27, 00:30'],
    ]) {
      expect(auditEntry(entry({ action: 'appointment.draft', details: { arrival } })).lines)
        .toContainEqual({ label: 'Ankunft', value: expected })
    }
  })
  it('keeps history readable for malformed arrival in older events', () => {
    expect(auditEntry(entry({ action: 'appointment.confirmed', details: { arrival: 'invalid' } })).lines)
      .toContainEqual({ label: 'Ankunft', value: 'Zeitpunkt nicht mehr verfügbar' })
  })
  it('explains driver reports and before/after time corrections', () => {
    expect(auditEntry(entry({ actor: 'driver', action: 'defect.reported' })).actor).toBe(
      'Über den Fahrerlink gemeldet',
    )
    const result = auditEntry(
      entry({
        action: 'time.corrected',
        details: {
          reason: 'Start berichtigt',
          before: { started: 1000, ended: 2000 },
          after: { started: 1100, ended: 2000 },
        },
      }),
    )
    expect(result.lines.map((line) => line.label)).toEqual([
      'Grund',
      'Bisherige Buchung',
      'Korrigierte Buchung',
    ])
  })
})
