import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, s: string) => s }))
const api = vi.hoisted(() => ({ list: vi.fn(), all: vi.fn(), command: vi.fn(), post: vi.fn(), upload: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    props: { disabled: Boolean },
    template: '<button type="button" :disabled="disabled"><slot /></button>',
  },
}))
vi.mock('../../src/components/Modal.vue', () => ({
  default: { template: '<section><slot /></section>' },
}))
import OrderDetail from '../../src/components/OrderDetail.vue'
import OrderComposer from '../../src/components/OrderComposer.vue'
import { deadlineState, total, localInput } from '../../src/format'
import type { Vehicle, Template, Deadline, Interval, Order, Employee } from '../../src/types'
const vehicle = { id: 'v1', plate: 'TEST 1', archived: false } as Vehicle
const template = {
  id: 't1',
  title: 'Inspektion',
  description: 'Prüfen',
  steps: ['Öl prüfen'],
  minutes: 45,
  favorite: true,
  position: 0,
  archived: false,
} as Template
beforeEach(() => {
  vi.resetAllMocks()
  localStorage.clear()
  api.list.mockResolvedValue({ items: [vehicle], more: false })
  api.all.mockImplementation((kind: string) =>
    Promise.resolve(
      kind === 'templates'
        ? [template]
        : [
            {
              id: 's1',
              title: 'Probefahrt',
              text: 'Probefahrt durchgeführt',
              favorite: true,
              position: 0,
              archived: false,
            },
          ],
    ),
  )
  api.command.mockResolvedValue({ id: 'order1' })
  api.post.mockResolvedValue({})
})
describe('quick order workflow', () => {
  it('vehicle context -> template -> release creates copied content and duration', async () => {
    const w = mount(OrderComposer, { props: { vehicle } })
    await flushPromises()
    const b = w.findAll('button').find((x) => x.text().includes('Inspektion'))!
    await b.trigger('click')
    await w
      .findAll('button')
      .find((x) => x.text() === 'Freigeben')!
      .trigger('click')
    await flushPromises()
    expect(api.command).toHaveBeenCalledWith(
      '/api/v1/orders',
      expect.objectContaining({
        vehicle_id: 'v1',
        title: 'Inspektion',
        steps: ['Öl prüfen'],
        minutes: 45,
        status: 'open',
      }),
    )
    expect(w.emitted('created')).toHaveLength(1)
  })
  it('adding a snippet preserves the selected duration', async () => {
    const w = mount(OrderComposer, { props: { vehicle } })
    await flushPromises()
    await w
      .findAll('button')
      .find((x) => x.text().includes('Inspektion'))!
      .trigger('click')
    await w
      .findAll('button')
      .find((x) => x.text().includes('Probefahrt'))!
      .trigger('click')
    await w
      .findAll('button')
      .find((x) => x.text() === 'Freigeben')!
      .trigger('click')
    await flushPromises()
    expect(api.command).toHaveBeenCalledWith(
      '/api/v1/orders',
      expect.objectContaining({ steps: ['Öl prüfen', 'Probefahrt durchgeführt'], minutes: 45 }),
    )
  })
  it('does not submit without a title', async () => {
    const w = mount(OrderComposer, { props: { vehicle } })
    await flushPromises()
    await w
      .findAll('button')
      .find((x) => x.text() === 'Freigeben')!
      .trigger('click')
    expect(api.command).not.toHaveBeenCalled()
    expect(w.find('[role="alert"]').text()).toContain('Titel')
  })
  it('retains entered content after a failed save', async () => {
    api.command.mockRejectedValue(new Error('Offline'))
    const w = mount(OrderComposer, { props: { vehicle } })
    await flushPromises()
    await w
      .findAll('button')
      .find((x) => x.text().includes('Inspektion'))!
      .trigger('click')
    await w
      .findAll('button')
      .find((x) => x.text() === 'Freigeben')!
      .trigger('click')
    await flushPromises()
    expect(w.find('[role="alert"]').text()).toBe('Offline')
    expect((w.find('input[required]').element as HTMLInputElement).value).toBe('Inspektion')
  })
})
describe('time and deadline display', () => {
  it('uses the edited date’s timezone offset across summer and winter', () => {
    const offset = vi.spyOn(Date.prototype, 'getTimezoneOffset').mockImplementation(function (
      this: Date,
    ) {
      return this.getUTCMonth() === 0 ? -60 : -120
    })
    try {
      expect(localInput('2026-01-15T12:00:00Z')).toBe('2026-01-15T13:00')
      expect(localInput('2026-07-15T12:00:00Z')).toBe('2026-07-15T14:00')
    } finally {
      offset.mockRestore()
    }
  })
  it('totals working intervals, excluding gap/pause', () => {
    const rows = [
      { started: 100, ended: 200 },
      { started: 400, ended: 500 },
    ] as Interval[]
    expect(total(rows)).toBe(200)
  })
  it('kilometre criterion can independently make deadline due', () => {
    expect(
      deadlineState(
        { status: 'open', due_date: '2027-01-01', due_km: 100, warning_days: [30, 7] } as Deadline,
        { mileage: 101 } as Vehicle,
        '2026-10-01',
      ),
    ).toBe('due')
  })
  it('warns within configured advance days', () => {
    expect(
      deadlineState(
        { status: 'open', due_date: '2026-10-20', due_km: null, warning_days: [30, 7] } as Deadline,
        vehicle,
        '2026-10-01',
      ),
    ).toBe('soon')
  })
})


describe('tablet guidance and confirmed feedback', () => {
  const order = { id: 'o1', vehicle_id: 'v1', title: 'Inspektion', status: 'open', steps: [], version: 1 } as unknown as Order
  const employees = [{ id: 'e1', name: 'Alex', archived: false }] as Employee[]
  it('explains why start is unavailable before choosing a name', async () => {
    api.all.mockResolvedValue([])
    const w = mount(OrderDetail, { props: { order, employees, master: false, vehicle } })
    await flushPromises()
    const start = w.findAll('button').find(b => b.text() === 'Arbeit starten')!
    expect(start.attributes('disabled')).toBeDefined()
    expect(w.text()).toContain('Bitte zuerst Ihren Namen auswählen')
    await w.find('select').setValue('e1')
    expect(start.attributes('disabled')).toBeUndefined()
  })
  it('explains a competing active order instead of permitting another start', async () => {
    api.all.mockImplementation((kind: string) => Promise.resolve(kind === 'active' ? [{ order_id: 'o2', employee_id: 'e1' }] : []))
    const w = mount(OrderDetail, { props: { order, employees, master: false, vehicle } })
    await flushPromises()
    await w.find('select').setValue('e1')
    expect(w.text()).toContain('Sie haben bereits einen laufenden Auftrag')
    expect(w.findAll('button').find(b => b.text() === 'Arbeit starten')!.attributes('disabled')).toBeDefined()
  })
  it('confirms the running timer after server success and resets the selected name', async () => {
    api.all.mockResolvedValue([])
    api.command.mockResolvedValue({ ...order, status: 'active', version: 2 })
    const w = mount(OrderDetail, { props: { order, employees, master: false, vehicle } })
    await flushPromises()
    await w.find('select').setValue('e1')
    await w.findAll('button').find(b => b.text() === 'Arbeit starten')!.trigger('click')
    await flushPromises()
    expect(w.find('[role="status"]').text()).toContain('Die Zeit läuft')
    expect((w.find('select').element as HTMLSelectElement).value).toBe('')
  })
  it('does not claim a successful booking after a failed request', async () => {
    api.all.mockResolvedValue([])
    api.command.mockRejectedValue(new Error('Keine Verbindung'))
    const w = mount(OrderDetail, { props: { order, employees, master: false, vehicle } })
    await flushPromises()
    await w.find('select').setValue('e1')
    await w.findAll('button').find(b => b.text() === 'Arbeit starten')!.trigger('click')
    await flushPromises()
    expect(w.text()).not.toContain('Die Zeit läuft')
    expect(w.find('[role="alert"]').text()).toContain('Keine Verbindung')
    expect((w.find('select').element as HTMLSelectElement).value).toBe('e1')
  })
  it('replaces the running notice after requesting parts and pausing', async () => {
    let started = false
    api.all.mockImplementation((kind: string) => Promise.resolve(
      kind === 'active' ? (started ? [{ order_id: 'o1', employee_id: 'e1' }] : [])
        : kind === 'orders' ? [{ ...order, status: 'paused', version: 3 }] : [],
    ))
    api.command.mockImplementationOnce(async () => {
      started = true
      return { ...order, status: 'active', version: 2 }
    }).mockResolvedValueOnce({ id: 'p1' })
    const w = mount(OrderDetail, { props: { order, employees, master: false, vehicle } })
    await flushPromises()
    await w.find('select').setValue('e1')
    await w.findAll('button').find(b => b.text() === 'Arbeit starten')!.trigger('click')
    await flushPromises()
    expect(w.find('[role="status"]').text()).toContain('Die Zeit läuft')
    await w.find('select').setValue('e1')
    await w.findAll('button').find(b => b.text() === 'Teil anfordern')!.trigger('click')
    await w.find('input[maxlength="255"]').setValue('Ölfilter')
    await w.findAll('button').find(b => b.text() === 'Anfordern und pausieren')!.trigger('click')
    await flushPromises()
    expect(w.find('[role="status"]').text()).toContain('Der Auftrag ist pausiert')
    expect(w.find('[role="status"]').text()).not.toContain('Die Zeit läuft –')
    expect((w.find('select').element as HTMLSelectElement).value).toBe('')
    expect(api.command).toHaveBeenLastCalledWith('/api/v1/actions/part-request', expect.objectContaining({ pause: true, title: 'Ölfilter' }))
  })

})
