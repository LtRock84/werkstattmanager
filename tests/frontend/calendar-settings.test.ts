import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, text: string) => text }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button :disabled="disabled"><slot /></button>' } }))

import CalendarSettings from '../../src/components/CalendarSettings.vue'

beforeEach(() => vi.resetAllMocks())

describe('automatic workshop calendar settings', () => {
  it('explains the zero-setup master calendar and keeps legacy credentials folded', async () => {
    api.get.mockResolvedValue({ mode: 'native', native_enabled: true, calendar_url: '', calendar_user: '', has_password: false })
    const wrapper = mount(CalendarSettings, { props: { admin: true } })
    await flushPromises()
    expect(wrapper.text()).toContain('Automatisch eingerichtet')
    expect(wrapper.text()).toContain('Ein zusätzliches Konto und App-Passwort sind nicht erforderlich')
    expect(wrapper.find('details').attributes('open')).toBeUndefined()
    wrapper.unmount()
  })

  it('switches a legacy installation back to the native calendar without retaining credentials', async () => {
    api.get.mockResolvedValue({ mode: 'external', native_enabled: false, calendar_url: 'https://cloud.example.test/remote.php/dav/calendars/legacy/workshop/', calendar_user: 'legacy', has_password: true })
    api.put.mockResolvedValue({ mode: 'native', native_enabled: true, calendar_url: '', calendar_user: '', has_password: false })
    const wrapper = mount(CalendarSettings, { props: { admin: true } })
    await flushPromises()
    expect(wrapper.text()).toContain('Externe Kalenderverbindung aktiv')
    expect(wrapper.text()).toContain('Entfernen Sie diese dort einmalig, damit Termine nicht doppelt erscheinen.')
    await wrapper.findAll('button').find((button) => button.text().includes('automatischen Werkstattkalender'))!.trigger('click')
    await flushPromises()
    expect(api.put).toHaveBeenCalledWith('/api/v1/calendar-settings', { data: { calendar_url: '', calendar_user: '', password: '', remove_password: true } })
    expect(wrapper.text()).toContain('Automatisch eingerichtet')
    wrapper.unmount()
  })
})
