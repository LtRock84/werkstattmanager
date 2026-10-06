import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { Appointment, ServiceLine, Template, Vehicle } from '../../src/types'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
const api = vi.hoisted(() => ({ list: vi.fn(), all: vi.fn(), command: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { template: '<button type="button"><slot /></button>' } }))
vi.mock('../../src/components/Modal.vue', () => ({ default: { template: '<div><slot /></div>' } }))
vi.mock('../../src/components/ServicePicker.vue', () => ({ default: { name: 'ServicePicker', props: ['modelValue'], emits: ['update:modelValue'], template: '<div />' } }))
import OrderComposer from '../../src/components/OrderComposer.vue'
import AppointmentEditor from '../../src/components/AppointmentEditor.vue'
import ServicePicker from '../../src/components/ServicePicker.vue'
const vehicle = { id: 'v1', plate: 'TEST', archived: false } as Vehicle
const template = { id: 't1', title: 'Inspektion', description: '', steps: ['Prüfen'], minutes: 45, favorite: true, position: 0, archived: false } as Template
const oil = { service_id: 'oil', key: 'oil', title: 'Ölwechsel', quantity: 1, minutes: 30, unit: 'Leistung', steps: [], vehicle_class: 'car' } as ServiceLine
const wheels = { ...oil, service_id: 'wheels', key: 'wheels', title: 'Räderwechsel', minutes: 40 }
beforeEach(() => {
  vi.resetAllMocks()
  api.all.mockImplementation(async (kind: string) => kind === 'templates' ? [template, { ...template, id: 't2', title: 'Kontrolle', minutes: null }] : kind === 'vehicles' ? [vehicle] : [])
  api.list.mockResolvedValue({ items: [vehicle] })
  api.command.mockResolvedValue({ id: 'saved' })
})
describe.each(['order', 'appointment'] as const)('%s service title', (kind) => {
  it('tracks multiple services and clears automatic title/duration after removal', async () => {
    const w = kind === 'order' ? mount(OrderComposer, { props: { vehicle } }) : mount(AppointmentEditor, { props: { day: '2026-12-01' } })
    await flushPromises()
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [oil]); await flushPromises()
    expect((w.find('input[required][maxlength="255"]').element as HTMLInputElement).value).toBe('Ölwechsel')
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [oil, wheels]); await flushPromises()
    expect((w.find('input[required][maxlength="255"]').element as HTMLInputElement).value).toBe('Ölwechsel / Räderwechsel')
    expect((w.find('input[type="number"]').element as HTMLInputElement).value).toBe('70')
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', []); await flushPromises()
    expect((w.find('input[required][maxlength="255"]').element as HTMLInputElement).value).toBe('')
    expect((w.find('input[type="number"]').element as HTMLInputElement).value).toBe('')
    w.unmount()
  })
  it('preserves a manually changed title when adding and removing services', async () => {
    const w = kind === 'order' ? mount(OrderComposer, { props: { vehicle } }) : mount(AppointmentEditor, { props: { day: '2026-12-01' } })
    await flushPromises()
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [oil]); await flushPromises()
    await w.find('input[required][maxlength="255"]').setValue('Kundenabsprache')
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [oil, wheels]); await flushPromises()
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', []); await flushPromises()
    expect((w.find('input[required][maxlength="255"]').element as HTMLInputElement).value).toBe('Kundenabsprache')
    w.unmount()
  })
})
describe('template and appointment regression', () => {
  it('adds extra service quantities to template time and restores template time after removal', async () => {
    const w = mount(OrderComposer, { props: { vehicle } }); await flushPromises()
    await w.findAll('button').find(b => b.text().includes('Inspektion'))!.trigger('click')
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [{ ...oil, quantity: 2 }]); await flushPromises()
    expect((w.find('input[type="number"]').element as HTMLInputElement).value).toBe('105')
    expect((w.find('input[required]').element as HTMLInputElement).value).toBe('Inspektion / Ölwechsel')
    await w.findAll('button').find(b => b.text() === 'Entwurf speichern')!.trigger('click'); await flushPromises()
    expect(api.command).toHaveBeenLastCalledWith('/api/v1/orders', expect.objectContaining({ minutes: 105, title: 'Inspektion / Ölwechsel' }))
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', []); await flushPromises()
    expect((w.find('input[type="number"]').element as HTMLInputElement).value).toBe('45')
    w.unmount()
  })
  it('preserves manual title across template changes and leaves unknown totals empty', async () => {
    const w = mount(OrderComposer, { props: { vehicle } }); await flushPromises()
    await w.find('input[required]').setValue('Kundenabsprache')
    await w.findAll('button').find(b => b.text().includes('Inspektion'))!.trigger('click')
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [{ ...oil, minutes: null }]); await flushPromises()
    expect((w.find('input[type="number"]').element as HTMLInputElement).value).toBe('')
    await w.findAll('button').find(b => b.text().includes('Kontrolle'))!.trigger('click')
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [oil]); await flushPromises()
    expect((w.find('input[required]').element as HTMLInputElement).value).toBe('Kundenabsprache')
    expect((w.find('input[type="number"]').element as HTMLInputElement).value).toBe('')
    w.unmount()
  })
  it('editing an existing appointment does not mutate its service snapshot', async () => {
    const appointment = { id: 'a1', title: 'Kundenabsprache', arrival: '2026-12-01T07:00:00Z', minutes: 30, service_lines: [oil], allocations: [] } as unknown as Appointment
    const w = mount(AppointmentEditor, { props: { day: '2026-12-01', appointment } }); await flushPromises()
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [oil, wheels]); await flushPromises()
    expect(appointment.service_lines).toEqual([oil])
    expect((w.find('input[required][maxlength="255"]').element as HTMLInputElement).value).toBe('Kundenabsprache')
    w.unmount()
  })
})
