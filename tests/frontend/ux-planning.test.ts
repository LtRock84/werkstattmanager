import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { bookRange, shiftBookDay, appointmentMinutesOn } from '../../src/planning'
import { useDirtySnapshot } from '../../src/dirty'
import type { Appointment, Customer } from '../../src/types'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
const api = vi.hoisted(() => ({ get: vi.fn(), all: vi.fn(), command: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
vi.mock('../../src/components/Modal.vue', () => ({ default: { name: 'Modal', props: ['dirty', 'locked'], template: '<div><slot /></div>' } }))
import AppointmentEditor from '../../src/components/AppointmentEditor.vue'
import EntityEditor from '../../src/components/EntityEditor.vue'
import AppointmentBook from '../../src/components/AppointmentBook.vue'
import ServicePicker from '../../src/components/ServicePicker.vue'
import Modal from '../../src/components/Modal.vue'
const appointment = { id: 'a1', version: 7, created_at: 0, updated_at: 0, vehicle_id: 'v1', title: 'Original', arrival: '2026-10-07T06:00:00Z', work_start: null, pickup: null, minutes: 70, allocations: [{ date: '2026-10-07', minutes: 35 }, { date: '2026-10-08', minutes: 35 }], service_lines: [], status: 'confirmed', stage: 'arrived', customer_email: 'original@example.invalid', reminder: false, note: '', override_reason: '', order_id: 'o1' } as Appointment
beforeEach(() => {
  vi.resetAllMocks()
  api.all.mockResolvedValue([]); api.get.mockResolvedValue({ items: [] }); api.command.mockResolvedValue({})
})
describe('safe planning and form workflows', () => {
  it('never skips short months, leap days or year boundaries', () => {
    for (const day of ['2026-10-28','2026-10-29','2026-10-30','2026-10-31']) expect(shiftBookDay(day,'month',1)).toBe('2026-11-01')
    expect(shiftBookDay('2026-03-31','month',-1)).toBe('2026-02-01')
    expect(shiftBookDay('2026-12-31','month',1)).toBe('2027-01-01')
    expect(shiftBookDay('2024-02-29','day',1)).toBe('2024-03-01')
    expect(bookRange('2026-10-25','week')).toEqual(['2026-10-19','2026-10-25'])
    expect(bookRange('2024-02-29','month')).toEqual(['2024-02-01','2024-02-29'])
    expect(bookRange('', 'week')).toEqual([])
  })
  it('shows daily allocation separately, using Berlin for unallocated work', () => {
    expect(appointmentMinutesOn(appointment,'2026-10-08')).toBe(35)
    expect(appointmentMinutesOn(appointment,'2026-10-09')).toBe(0)
    expect(appointmentMinutesOn({ ...appointment, allocations: [], work_start: '2026-10-07T22:30:00Z' },'2026-10-08')).toBe(70)
  })
  it('protects changed values, stops warning after revert and removes unload listener', async () => {
    const state = ref({ text: '' })
    const w = mount(defineComponent({ setup() { const { dirty, reset } = useDirtySnapshot(() => state.value); return { dirty, reset } }, template: '<span>{{ dirty }}</span>' }))
    const event = () => new Event('beforeunload', { cancelable: true })
    state.value.text = 'note'; await flushPromises(); const changed = event(); window.dispatchEvent(changed); expect(changed.defaultPrevented).toBe(true)
    state.value.text = ''; await flushPromises(); const reverted = event(); window.dispatchEvent(reverted); expect(reverted.defaultPrevented).toBe(false)
    state.value.text = 'new'; w.unmount(); const closed = event(); window.dispatchEvent(closed); expect(closed.defaultPrevented).toBe(false)
  })
  it('cancels only original stored data after explicit confirmation', async () => {
    const w = mount(AppointmentEditor, { props: { appointment, day: '2026-10-07' } }); await flushPromises()
    await w.find('input[required][maxlength="255"]').setValue('Unsaved edit')
    await w.findAll('button').find(b => b.text() === 'Termin absagen')!.trigger('click')
    expect(api.command).not.toHaveBeenCalled()
    await w.findAll('button').find(b => b.text() === 'Weiter bearbeiten')!.trigger('click')
    expect(w.find('input[required][maxlength="255"]').element).toHaveProperty('value','Unsaved edit')
    await w.findAll('button').find(b => b.text() === 'Termin absagen')!.trigger('click')
    await w.findAll('button').find(b => b.text() === 'Absage bestätigen')!.trigger('click'); await flushPromises()
    expect(api.command).toHaveBeenCalledWith('/api/v1/appointments', { ...appointment, status: 'cancelled' }); w.unmount()
  })
  it('carries new customer identity into the vehicle form and protects close', async () => {
    const w = mount(AppointmentEditor, { props: { day: '2026-10-07' } }); await flushPromises()
    await w.findAll('button').find(b => b.text().includes('Neuer Kunde'))!.trigger('click')
    w.findComponent(EntityEditor).vm.$emit('saved', { id: 'new-customer', email: 'new@example.invalid' } as Customer); await flushPromises()
    const vehicle = w.findComponent(EntityEditor)
    expect(vehicle.props('customerId')).toBe('new-customer')
    expect(vehicle.find('select').element).toHaveProperty('value','customer')
    expect(vehicle.findComponent(Modal).props('dirty')).toBe(false)
    await vehicle.find('input').setValue('TEST'); expect(vehicle.findComponent(Modal).props('dirty')).toBe(true)
    const confirm = vi.spyOn(window,'confirm').mockReturnValue(false)
    await vehicle.findAll('button').find(b => b.text() === 'Abbrechen')!.trigger('click'); expect(w.findComponent(EntityEditor).exists()).toBe(true)
    confirm.mockRestore(); w.unmount()
  })
  it('adds independent free work without catalog mutation', async () => {
    const w = mount(ServicePicker, { props: { modelValue: [], vehicleClass: 'van' } }); await flushPromises()
    await w.find('input[maxlength="255"]').setValue('Zusatzdiagnose')
    await w.find('details input[type="number"]').setValue(35)
    await w.findAll('button').find(b => b.text() === 'Eigene Arbeit übernehmen')!.trigger('click')
    expect(w.emitted('update:modelValue')?.[0][0]).toEqual([expect.objectContaining({ service_id: '', title: 'Zusatzdiagnose', minutes: 35, quantity: 1, vehicle_class: 'van' })])
    expect(api.post).not.toHaveBeenCalled(); w.unmount()
  })
  it('shows linked completion and changes stage using exact stored version and snapshots', async () => {
    api.get.mockImplementation(async (url: string) => url.includes('/appointments?') ? { items: [appointment], days: [{ date: '2026-10-08', used:35, minutes:480, free:445, percent:7, vehicle_count:0 }], settings: { settings: { weekdays: {} } } } : url.includes('/records/orders') ? { items: [{ id:'o1',status:'completed' }] } : { items: [] })
    const w = mount(AppointmentBook); await flushPromises()
    expect(w.text()).toContain('An diesem Tag: 35 min'); expect(w.text()).toContain('Arbeitsauftrag: Abgeschlossen')
    await w.findAll('button').find(b => b.text() === 'Abholbereit setzen')!.trigger('click'); await flushPromises()
    expect(api.command).toHaveBeenCalledWith('/api/v1/appointments', { ...appointment, stage:'ready' }); w.unmount()
  })
})
