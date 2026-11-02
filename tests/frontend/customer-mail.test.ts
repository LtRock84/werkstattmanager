import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, s: string) => s }))
const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
import Settings from '../../src/components/CustomerMailSettings.vue'
const fixture = () => ({ version: 0, reminder_days: 30, placeholders: ['kundenname', 'fahrzeug', 'termin'], templates: { confirm: { label: 'Terminbestätigung', subject: 'Termin {fahrzeug}', body: 'Hallo {kundenname}, {termin}' }, ready: { label: 'Abholbereit', subject: 'Fertig {fahrzeug}', body: 'Hallo {kundenname}' }, pickup_print: { label: 'Abholschein (Druck)', subject: 'Abholschein {fahrzeug}', body: 'Bitte {kundenname} den QR-Code vorzeigen.' } } })
beforeEach(() => { vi.resetAllMocks(); api.get.mockImplementation(async () => fixture()); api.put.mockImplementation(async (_url, data) => ({ ...data.data, version: 1 })); vi.spyOn(window, 'confirm').mockReturnValue(true) })
const button = (w: ReturnType<typeof mount>, text: string) => w.findAll('button').find(b => b.text() === text)!
describe('customer mail settings', () => {
  it('preserves edited text when changing templates and saves all templates with the original version', async () => {
    const w = mount(Settings); await flushPromises()
    await w.find('textarea').setValue('Eigener Text {kundenname}')
    await w.find('select').setValue('ready'); await w.find('input[maxlength="255"]').setValue('Bereit {fahrzeug}')
    await w.find('select').setValue('confirm'); expect(w.find('textarea').element.value).toBe('Eigener Text {kundenname}')
    await button(w, 'Vorlagen speichern').trigger('click'); await flushPromises()
    expect(api.put.mock.calls[0][1].data).toMatchObject({ version: 0, templates: { ready: { subject: 'Bereit {fahrzeug}' }, confirm: { body: 'Eigener Text {kundenname}' } } })
    expect(w.text()).toContain('Vorlagen gespeichert'); expect(button(w, 'Vorlagen speichern').attributes('disabled')).toBeDefined(); w.unmount()
  })
  it('retains inputs on conflict and does not discard unsaved edits when reload is cancelled', async () => {
    const w = mount(Settings); await flushPromises(); await w.find('textarea').setValue('Nicht verlieren')
    api.put.mockRejectedValue(new Error('Inzwischen geändert')); await button(w, 'Vorlagen speichern').trigger('click'); await flushPromises()
    expect(w.text()).toContain('Inzwischen geändert'); expect(w.find('textarea').element.value).toBe('Nicht verlieren')
    vi.mocked(window.confirm).mockReturnValue(false); await button(w, 'Neu laden').trigger('click'); await flushPromises(); expect(api.get).toHaveBeenCalledTimes(1)
    expect(w.find('textarea').element.value).toBe('Nicht verlieren'); w.unmount()
  })
  it('edits and saves the pickup print template alongside the existing mail templates', async () => {
    const w = mount(Settings); await flushPromises()
    await w.find('select').setValue('pickup_print')
    expect(w.text()).toContain('Überschrift des Abholscheins')
    await w.find('textarea').setValue('Eigener Abholschein für {kundenname}')
    await button(w, 'Vorlagen speichern').trigger('click'); await flushPromises()
    expect(api.put.mock.calls[0][1].data.templates.pickup_print.body).toBe('Eigener Abholschein für {kundenname}')
    expect(api.put.mock.calls[0][1].data.templates.confirm.body).toBe(fixture().templates.confirm.body)
    w.unmount()
  })
  it('undo restores saved templates and explains existing customer defaults and mileage limitations', async () => {
    const w = mount(Settings); await flushPromises(); await w.find('textarea').setValue('Verwerfen'); await button(w, 'Änderungen verwerfen').trigger('click')
    expect(w.find('textarea').element.value).toBe(fixture().templates.confirm.body)
    expect(w.text()).toContain('bestehenden Kunden'); expect(w.text()).toContain('bestätigtem Kilometerstand'); expect(w.text()).toContain('{kundenname}'); w.unmount()
  })
})
