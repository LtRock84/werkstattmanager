import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { Employee, Order, Vehicle, Audit } from '../../src/types'
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, s: string) => s }))
const api = vi.hoisted(() => ({ all: vi.fn(), list: vi.fn(), get: vi.fn(), command: vi.fn(), post: vi.fn(), upload: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
vi.mock('../../src/components/Modal.vue', () => ({ default: { props: ['dirty'], template: '<section><slot /></section>' } }))
vi.mock('../../src/components/ServicePicker.vue', () => ({ default: { template: '<div />' } }))
vi.mock('../../src/components/ExistingWorkNotice.vue', () => ({ default: { template: '<div />' } }))
import EmployeePlan from '../../src/components/EmployeePlan.vue'
import OrderComposer from '../../src/components/OrderComposer.vue'
import OrderDetail from '../../src/components/OrderDetail.vue'
import Modal from '../../src/components/Modal.vue'
import { auditEntry } from '../../src/audit'
const base = (id: string) => ({ id, version: 1, created_at: 1, updated_at: 1 })
const employees: Employee[] = [{ ...base('e1'), name: 'Alex', archived: false }, { ...base('e2'), name: 'Robin', archived: false }]
const vehicle: Vehicle = { ...base('v1'), customer_id: null, kind: 'fleet', plate: 'DEMO', number: '', vin: '', make: '', model: '', registration: null, mileage: null, mileage_date: null, notes: '', archived: false }
const order: Order = { ...base('o1'), vehicle_id: 'v1', title: 'Prüfen', status: 'open', steps: [], description: '', planned_employee_id: 'e1', last_employee_id: null, minutes: 45, planned_start: null, due_at: null, priority: 'normal', pause_reason: null, completed_at: null }
beforeEach(() => {
  vi.resetAllMocks()
  api.all.mockImplementation((kind: string) => Promise.resolve(kind === 'employees' ? employees : []))
  api.list.mockResolvedValue({ items: [vehicle], more: false })
})
describe('optional employee planning', () => {
  it('shows an escaped name and explicitly permits takeover', () => {
    const w = mount(EmployeePlan, { props: { order, employees: [{ ...employees[0], name: '<img src=x onerror=alert(1)>' }] } })
    expect(w.text()).toContain('Vorgeplant: <img')
    expect(w.text()).toContain('Kollegen dürfen übernehmen')
    expect(w.find('img').exists()).toBe(false)
  })
  it('does not present old completed work or an unplanned order as currently planned', () => {
    for (const current of [{ ...order, status: 'completed' as const }, { ...order, planned_employee_id: null }]) {
      const w = mount(EmployeePlan, { props: { order: current, employees } })
      expect(w.find('.wm-employee-plan').exists()).toBe(false)
    }
  })
  it('explains unavailable or archived employees without preventing another worker', () => {
    for (const people of [[], [{ ...employees[0], archived: true }]]) {
      const w = mount(EmployeePlan, { props: { order, employees: people } })
      expect(w.text()).toContain('Bitte neu planen')
      expect(w.text()).toContain('Kollegen dürfen übernehmen')
    }
  })
  it('includes optional planning in new orders without starting a timer', async () => {
    api.command.mockResolvedValue(order)
    const w = mount(OrderComposer, { props: { vehicle } }); await flushPromises()
    await w.find('input[required]').setValue('Prüfen')
    await w.findAll('label').find(l => l.text().startsWith('Mitarbeiter vorplanen'))!.find('select').setValue('e1')
    await w.findAll('button').find(b => b.text() === 'Freigeben')!.trigger('click'); await flushPromises()
    expect(api.command).toHaveBeenCalledWith('/api/v1/orders', expect.objectContaining({ planned_employee_id: 'e1', status: 'open' }))
    expect(api.command).toHaveBeenCalledTimes(1)
  })
  it('retains a failed planning edit and confirms it only after server receipt', async () => {
    api.command.mockRejectedValueOnce(new Error('Inzwischen geändert')).mockResolvedValueOnce({ ...order, version: 2, planned_employee_id: 'e2' })
    const w = mount(OrderDetail, { props: { order, vehicle, employees, master: true } }); await flushPromises()
    const select = w.findAll('label').find(l => l.text().startsWith('Vorgesehener Mitarbeiter'))!.find('select')
    await select.setValue('e2')
    const save = () => w.findAll('button').find(b => b.text() === 'Vorplanung speichern')!
    await save().trigger('click'); await flushPromises()
    expect(w.text()).toContain('Inzwischen geändert')
    expect((select.element as HTMLSelectElement).value).toBe('e2')
    expect(w.emitted('changed')).toBeUndefined()
    await save().trigger('click'); await flushPromises()
    expect(api.command).toHaveBeenLastCalledWith('/api/v1/orders/o1/plan', { version: 1, planned_employee_id: 'e2' })
    expect(w.text()).toContain('Vorplanung gespeichert')
    expect(w.findComponent(Modal).props('dirty')).toBe(false)
    expect(save().attributes('disabled')).toBeDefined()
    expect(w.emitted('changed')).toHaveLength(1)
  })
  it('requires the tablet worker to choose their own name and permits a different employee', async () => {
    api.command.mockResolvedValue({ ...order, version: 2, status: 'active', last_employee_id: 'e2' })
    const w = mount(OrderDetail, { props: { order, vehicle, employees, master: false } }); await flushPromises()
    expect(w.text()).not.toContain('Vorplanung speichern')
    const select = w.findAll('label').find(l => l.text().startsWith('Wer arbeitet'))!.find('select')
    expect((select.element as HTMLSelectElement).value).toBe('')
    await select.setValue('e2')
    await w.findAll('button').find(b => b.text() === 'Arbeit starten')!.trigger('click'); await flushPromises()
    expect(api.command).toHaveBeenCalledWith('/api/v1/orders/o1/start', expect.objectContaining({ employee_id: 'e2' }))
  })
  it('explains planning changes and clearing in history without exposing raw IDs', () => {
    const item: Audit = { id: 'a1', version: 1, created_at: 1, updated_at: 1, actor: 'master', action: 'order.plan', employee_id: null, order_id: 'o1', details: { before_employee_id: 'e1', after_employee_id: null }, context: { employee: null, previous_employee: { name: 'Alex' }, order: null, part: null, defect: null, deadline: null } }
    const entry = auditEntry(item)
    expect(entry.title).toContain('aufgehoben')
    expect(JSON.stringify(entry)).toContain('Alex')
    expect(JSON.stringify(entry)).toContain('Niemand')
    expect(JSON.stringify(entry)).not.toContain('e1')
  })
})
