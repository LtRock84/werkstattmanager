import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, s: string) => s }))
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({
  ...api,
  message: (e: Error) => e.message,
  url: (p: string) => p,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    props: { disabled: Boolean },
    template: '<button type="button" :disabled="disabled"><slot /></button>',
  },
}))
vi.mock('../../src/components/Modal.vue', () => ({
  default: { template: '<aside><slot /></aside>' },
}))
import Statistics from '../../src/components/WorkshopStatistics.vue'
const summary = {
  actual_cents: 20000,
  planned_cents: 12345,
  missing_actual: 1,
  missing_plan: 2,
  completed: 1,
  planned_jobs: 2,
  work_seconds: 7200,
  fleet_seconds: 1800,
  capacity_minutes: 480,
  booked_minutes: 600,
  completed_planned_minutes: 60,
  completed_work_seconds: 5400,
  comparable_completed: 1,
  long_intervals: 1,
  undated_jobs: 1,
}
function fixture() {
  return {
    from: '2026-10-09',
    to: '2026-10-09',
    summary: { ...summary },
    months: [{ month: '2026-10', actual_cents: 20000, planned_cents: 12345 }],
    days: [],
    employees: [],
    completed: [],
    jobs: [
      {
        source_kind: 'orders',
        source_id: 'a',
        title: 'Inspektion',
        vehicle_label: 'KUNDE',
        vehicle_kind: 'customer',
        status: 'completed',
        date: '2026-10-09',
        completed_at: '2026-10-09T08:00:00Z',
        valuation: null,
      },
      {
        source_kind: 'orders',
        source_id: 'f',
        title: 'Flotte',
        vehicle_label: 'FLO 1',
        vehicle_kind: 'fleet',
        status: 'active',
        date: null,
        valuation: null,
      },
    ],
  }
}
beforeEach(() => {
  vi.resetAllMocks()
  api.get.mockResolvedValue(fixture())
  api.post.mockResolvedValue({})
})
const button = (w: ReturnType<typeof mount>, text: string) =>
  w.findAll('button').find((b) => b.text() === text)!
describe('workshop reporting', () => {
  it('shows missing amounts, overbooking, full-lifetime comparison and no fleet money action', async () => {
    const w = mount(Statistics)
    await flushPromises()
    expect(w.text()).toContain('125 %')
    expect(w.text()).toContain('Fehlende Beträge')
    expect(w.text()).toContain('nicht als 0 €')
    expect(w.text()).toContain('Interne Flottenarbeit')
    expect(w.findAll('button').filter((b) => b.text() === 'Beträge erfassen')).toHaveLength(1)
    expect(w.text()).toContain('auch vor dem Zeitraum')
    expect(w.text()).toContain('noch keinen Planungstag')
  })
  it('saves exact decimal cents and dates only after explicit entry and retains input on conflict', async () => {
    const w = mount(Statistics)
    await flushPromises()
    await button(w, 'Beträge erfassen').trigger('click')
    const modal = w.find('aside')
    await modal.findAll('input')[0].setValue('250,01')
    await modal.findAll('input')[1].setValue('200.50')
    await modal.find('textarea').setValue('Rechnung übernommen')
    api.post.mockRejectedValueOnce(new Error('Gleichzeitige Änderung'))
    await button(w, 'Beträge speichern').trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/v1/statistics/revenue', {
      data: expect.objectContaining({
        source_kind: 'orders',
        source_id: 'a',
        version: 0,
        planned_net_cents: 25001,
        actual_net_cents: 20050,
        revenue_date: '2026-10-09',
        reason: 'Rechnung übernommen',
      }),
    })
    expect(w.find('aside').exists()).toBe(true)
    expect(w.find('aside input').element).toHaveProperty('value', '250,01')
    expect(w.text()).toContain('Gleichzeitige Änderung')
  })
  it('blocks invalid dates and precision, allows a missing amount rather than inferring prices', async () => {
    const w = mount(Statistics)
    await flushPromises()
    await button(w, 'Beträge erfassen').trigger('click')
    await w.find('aside input').setValue('12,345')
    await w.find('aside textarea').setValue('Preis')
    await button(w, 'Beträge speichern').trigger('click')
    await flushPromises()
    expect(api.post).not.toHaveBeenCalled()
    await w.find('aside input').setValue('')
    await button(w, 'Beträge speichern').trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith(expect.anything(), {
      data: expect.objectContaining({ planned_net_cents: null, actual_net_cents: null }),
    })
    expect(w.find('aside').exists()).toBe(false)
  })
  it('hides old totals when loading a period fails rather than showing them as current', async () => {
    const w = mount(Statistics)
    await flushPromises()
    api.get.mockRejectedValueOnce(new Error('Keine Verbindung'))
    await button(w, 'Auswertung anzeigen').trigger('click')
    await flushPromises()
    expect(w.text()).toContain('Keine Verbindung')
    expect(w.text()).not.toContain('Erfasster Ist-Umsatz')
  })
})

it('asks before discarding edited financial values', async () => {
  const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
  const w = mount(Statistics)
  await flushPromises()
  await button(w, 'Beträge erfassen').trigger('click')
  await w.find('aside input').setValue('99,00')
  await button(w, 'Abbrechen').trigger('click')
  expect(confirm).toHaveBeenCalledOnce()
  expect(w.find('aside').exists()).toBe(true)
  confirm.mockReturnValue(true)
  await button(w, 'Abbrechen').trigger('click')
  expect(w.find('aside').exists()).toBe(false)
  confirm.mockRestore()
})
