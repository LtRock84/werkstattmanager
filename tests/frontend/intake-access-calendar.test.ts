import { beforeEach, afterEach, describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { Appointment, CapacityDay, ServiceDefinition } from '../../src/types'
const api = vi.hoisted(() => ({ get: vi.fn(), all: vi.fn(), put: vi.fn(), command: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, text: string, values: Record<string, unknown> = {}) => text.replace(/\{(\w+)\}/g, (_, key) => String(values[key] ?? `{${key}}`)) }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
vi.mock('../../src/components/Modal.vue', () => ({ default: { template: '<div><slot /></div>' } }))
import AppointmentCalendar from '../../src/components/AppointmentCalendar.vue'
import AppointmentEditor from '../../src/components/AppointmentEditor.vue'
import AppointmentBook from '../../src/components/AppointmentBook.vue'
import AccessSettings from '../../src/components/AccessSettings.vue'
import ServicePicker from '../../src/components/ServicePicker.vue'
const day = (date: string, extra: Partial<CapacityDay> = {}): CapacityDay => ({ date, used: 120, minutes: 480, free: 360, percent: 25, vehicle_count: 2, max_vehicles: 5, closed: false, overbooked: false, override: null, ...extra })
const a = { id: 'a1', version: 1, created_at: 0, updated_at: 0, work_start: null, title: 'Inspektion', arrival: '2026-10-06T06:00:00Z', pickup: '2026-10-06T14:00:00Z', allocations: [{ date: '2026-10-06', minutes: 120 }], minutes: 120, service_lines: [], vehicle_id: 'v1', status: 'confirmed', stage: 'planned', order_id: null, note: '', customer_email: '', reminder: false, override_reason: '' } as Appointment
const data = () => ({ items: [a], days: [day('2026-10-05'), day('2026-10-06'), day('2026-10-07', { free: 0, percent: 100 }), day('2026-10-10', { closed: true })], settings: { settings: { weekdays: {} } } })
beforeEach(() => { vi.resetAllMocks(); api.get.mockImplementation(async (url: string) => url.includes('/appointments?') ? data() : { items: [] }); api.all.mockResolvedValue([]); api.command.mockResolvedValue(a) })
afterEach(() => { vi.useRealTimers() })
describe('calendar inside appointment intake', () => {
  it('shows weekdays, arrival, collection, capacities and explicit selection', async () => {
    const w = mount(AppointmentCalendar, { props: { day: '2026-10-06', minutes: 60 } }); await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/api/v1/appointments?from=2026-10-05&to=2026-10-11')
    expect(w.text()).toContain('Dienstag, 06.10.2026'); expect(w.text()).toContain('6 von 8 Stunden frei'); expect(w.text()).toContain('25 %'); expect(w.text()).toContain('Inspektion'); expect(w.text()).toContain('08:00'); expect(w.text()).toContain('16:00'); expect(w.emitted('choose')).toBeUndefined()
    await w.findAll('article')[0].find('button').trigger('click'); expect(w.emitted('choose')?.[0]).toEqual(['2026-10-05'])
    expect(w.findAll('article')[3].find('button').attributes('disabled')).toBeDefined(); w.unmount()
  })
  it('moves week across month and DST boundaries without changing selected day', async () => {
    const w = mount(AppointmentCalendar, { props: { day: '2026-10-25', minutes: null } }); await flushPromises()
    await w.find('button[aria-label="Nächste Kalenderwoche"]').trigger('click'); await flushPromises()
    expect(api.get).toHaveBeenLastCalledWith('/api/v1/appointments?from=2026-10-26&to=2026-11-01'); expect(w.emitted('choose')).toBeUndefined(); w.unmount()
  })
  it('rejects stale responses after calendar navigation and offers retry on failure', async () => {
    let finish!: (v: unknown) => void
    api.get.mockReturnValueOnce(new Promise(resolve => { finish = resolve }))
    const w = mount(AppointmentCalendar, { props: { day: '2026-10-06', minutes: 60 } })
    api.get.mockRejectedValueOnce(new Error('Netzwerk nicht erreichbar')); await w.find('button[aria-label="Nächste Kalenderwoche"]').trigger('click'); await flushPromises()
    finish(data()); await flushPromises(); expect(w.text()).toContain('Netzwerk nicht erreichbar'); expect(w.findAll('article')).toHaveLength(0)
    await w.findAll('button').find(b => b.text() === 'Auslastung aktualisieren')!.trigger('click'); await flushPromises(); expect(w.findAll('article')).toHaveLength(4); w.unmount()
  })
  it('keeps multiday dates protected and counts the existing allocation when checking an edited appointment', async () => {
    api.get.mockResolvedValue({ ...data(), days: [day('2026-10-06', { free: 0, vehicle_count: 5 })] })
    const w = mount(AppointmentCalendar, { props: { day: '2026-10-06', minutes: 120, appointmentId: 'a1', multiday: true } }); await flushPromises()
    expect(w.text()).not.toContain('nicht genügend'); expect(w.find('article button').attributes('disabled')).toBeDefined(); w.unmount()
  })
  it('uses calendar choice to change arrival, preserves title and confirms normal saving', async () => {
    api.all.mockImplementation(async (kind: string) => kind === 'vehicles' ? [{ id: 'v1', plate: 'TEST' }] : [])
    const w = mount(AppointmentEditor, { props: { day: '2026-10-06', reception: true } }); await flushPromises()
    await w.find('select').setValue('v1'); await w.find('input[maxlength="255"][required]').setValue('Eigene Absprache'); await w.find('input[type="number"]').setValue(60)
    await w.findAll('input[type="datetime-local"]')[1].setValue('2026-10-06T16:00')
    await w.findComponent(AppointmentCalendar).findAll('article')[0].find('button').trigger('click')
    expect(w.find('input[type="datetime-local"]').element).toHaveProperty('value', '2026-10-05T08:00'); expect(w.findAll('input[type="datetime-local"]')[1].element).toHaveProperty('value', '')
    await w.find('form').trigger('submit'); await flushPromises()
    expect(api.command).toHaveBeenCalledWith('/api/v1/appointments', expect.objectContaining({ status: 'confirmed', title: 'Eigene Absprache', arrival: '2026-10-05T06:00:00.000Z' }))
    expect(w.text()).not.toContain('Neuer Kunde'); expect(w.text()).not.toContain('Begründung für Überbuchung'); expect(w.findComponent(ServicePicker).props('hidePrices')).toBe(true); w.unmount()
  })
})
describe('dynamic service suggestions and reception controls', () => {
  it('places usage ahead of favourites, limits suggestions to eight and searches the complete catalogue', async () => {
    const items = Array.from({ length: 10 }, (_, i) => ({ id: String(i), seed_key: String(i), title: 'Leistung ' + i, synonyms: [], category: 'Prüfung', archived: false, vehicle_classes: ['car'], usage_count: i, favorite: i === 0, position: i, steps: [], includes: [] } as unknown as ServiceDefinition))
    api.get.mockResolvedValue({ items })
    const w = mount(ServicePicker, { props: { modelValue: [], hidePrices: true } }); await flushPromises()
    const buttons = w.findAll('.wm-service-result button'); expect(buttons).toHaveLength(8); expect(buttons[0].text()).toBe('+ Leistung 9'); expect(buttons[7].text()).toBe('+ Leistung 2'); expect(w.text()).not.toContain('Preise / Quelle')
    await w.find('input[type="search"]').setValue('Leistung 0'); expect(w.findAll('.wm-service-result button')[0].text()).toBe('+ Leistung 0'); w.unmount()
  })
  it('reception book avoids forbidden endpoints and hides all workshop/capacity actions', async () => {
    const w = mount(AppointmentBook, { props: { reception: true } }); await flushPromises()
    expect(api.get.mock.calls.some(([url]) => url.includes('/deliveries') || url.includes('/records/orders'))).toBe(false)
    expect(w.text()).toContain('Bestätigt'); expect(w.text()).toContain('Dienstag'); expect(w.text()).not.toContain('Arbeitsauftrag erstellen'); expect(w.text()).not.toContain('Tageskapazität einstellen'); expect(w.text()).not.toContain('Kapazität ändern'); expect(w.text()).not.toContain('Ankunft bestätigen'); w.unmount()
  })
  it('does not load privileged account directory for nonadministrators', async () => {
    const w = mount(AccessSettings, { props: { admin: false } }); await flushPromises(); expect(api.get).not.toHaveBeenCalled(); expect(w.text()).toContain('Nextcloud-Administrator'); w.unmount()
  })
  it('loads administrator controls when parent permissions arrive after mounting', async () => {
    api.get.mockResolvedValue({ master_group: 'meister', tablet_group: 'tablet', reception_group: '', master_users: [], reception_users: [] })
    const w = mount(AccessSettings, { props: { admin: false } }); await flushPromises(); expect(api.get).not.toHaveBeenCalled()
    await w.setProps({ admin: true }); await flushPromises(); expect(api.get).toHaveBeenCalledWith('/api/v1/access-settings'); expect(w.text()).toContain('Zugänge speichern'); w.unmount()
  })
  it('requires explicit save after assigning searched users and stores only identifiers', async () => {
    vi.useFakeTimers()
    api.get.mockImplementation(async (url: string) => url.includes('/access-users') ? { items: [{ id: 'assistant', name: 'Assistenz Demo' }] } : { master_group: 'meister', tablet_group: 'tablet', reception_group: '', master_users: [], reception_users: [] })
    const w = mount(AccessSettings, { props: { admin: true } }); await flushPromises()
    await w.find('input[type="search"]').setValue('Assistenz'); await vi.advanceTimersByTimeAsync(250); await flushPromises()
    await w.findAll('button').find(b => b.text().includes('Assistenz Demo'))!.trigger('click'); expect(api.put).not.toHaveBeenCalled()
    await w.findAll('button').find(b => b.text() === 'Zugänge speichern')!.trigger('click'); await flushPromises()
    expect(api.put).toHaveBeenCalledWith('/api/v1/access-settings', { data: { master_group: 'meister', tablet_group: 'tablet', reception_group: '', master_users: [], reception_users: ['assistant'] } }); w.unmount()
  })
})
