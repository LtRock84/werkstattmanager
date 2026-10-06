import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), url: (s: string) => '/apps/werkstattmanager' + s }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (_app: string, _key: string, fallback: unknown) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, text: string) => text }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean, type: String }, template: '<button :type="type || \'button\'" :disabled="disabled"><slot /></button>' } }))
import { companyProfile } from '../../src/company'
import CompanyHeader from '../../src/components/CompanyHeader.vue'
import CompanySettings from '../../src/components/CompanySettings.vue'
import AppointmentBook from '../../src/components/AppointmentBook.vue'
import MailSettings from '../../src/components/MailSettings.vue'
beforeEach(() => { vi.resetAllMocks(); companyProfile.value = { name: '', logo: '' } })
describe('shared company identity and sender', () => {
  it('shows provider handoff distinctly without a duplicate-send action', async () => {
    api.get.mockImplementation(async (path: string) => path.includes('/deliveries') ? { items: [], submitted: [{ id: 'd1', updated_at: 1791280000, context: { title: 'Mailtermin' } }] } : { items: [], days: [] })
    const w = mount(AppointmentBook); await flushPromises(); expect(w.text()).toContain('Mailtermin'); expect(w.text()).toContain('keine Zustellbestätigung'); expect(w.text()).not.toContain('Zustellung erneut versuchen'); w.unmount()
  })
  it('uses text binding and same-origin logo; updates the header immediately', async () => {
    const w = mount(CompanyHeader); expect(w.text()).toBe('')
    companyProfile.value = { name: '<script>Werkstatt</script>', logo: 'a'.repeat(64) }; await flushPromises()
    expect(w.text()).toBe('<script>Werkstatt</script>'); expect(w.find('script').exists()).toBe(false)
    expect(w.find('img').attributes('src')).toBe('/apps/werkstattmanager/public/v1/company/logo/' + 'a'.repeat(64)); expect(w.find('img').attributes('alt')).toBe(''); w.unmount()
  })
  it('saves a company name and publishes only server-confirmed data', async () => {
    api.post.mockResolvedValue({ name: 'Werkstatt GmbH', logo: '' }); const w = mount(CompanySettings)
    await w.find('input[maxlength]').setValue(' Werkstatt GmbH '); await w.find('form').trigger('submit'); await flushPromises()
    const payload = api.post.mock.calls[0][1] as FormData; expect(payload.get('name')).toBe(' Werkstatt GmbH '); expect(payload.get('remove_logo')).toBe('0'); expect(companyProfile.value.name).toBe('Werkstatt GmbH'); expect(w.text()).toContain('Firmenprofil gespeichert'); w.unmount()
  })
  it('preserves unsaved input and old identity after a rejected save', async () => {
    companyProfile.value = { name: 'Alt', logo: 'a'.repeat(64) }; api.post.mockRejectedValue(new Error('Logo ungültig')); const w = mount(CompanySettings)
    await w.find('input[maxlength]').setValue('Neu'); await w.find('form').trigger('submit'); await flushPromises()
    expect(companyProfile.value.name).toBe('Alt'); expect((w.find('input[maxlength]').element as HTMLInputElement).value).toBe('Neu'); expect(w.text()).toContain('Logo ungültig'); w.unmount()
  })
  it('loads sender accounts after admin flag arrives and saves explicit shared selection', async () => {
    const data = { selection: { mode: 'system' }, accounts: [{ id: '2', address: 'werkstatt@example.test', label: 'Firma' }] }
    api.get.mockResolvedValue(data); api.put.mockResolvedValue({ ...data, selection: { mode: 'mail', service: '2', address: 'werkstatt@example.test' } })
    const w = mount(MailSettings, { props: { admin: false } }); expect(api.get).not.toHaveBeenCalled()
    await w.setProps({ admin: true }); await flushPromises(); await w.findAll('select')[0].setValue('mail'); await w.findAll('select')[1].setValue('2')
    await w.find('button').trigger('click'); await flushPromises(); expect(api.put).toHaveBeenCalledWith('/api/v1/mail-settings', { data: { mode: 'mail', service: '2' } }); expect(w.text()).toContain('werkstatt@example.test'); expect(w.text()).toContain('keine Zustellbestätigung'); w.unmount()
  })
  it('blocks mail selection without configured account and shows provider errors', async () => {
    api.get.mockResolvedValue({ selection: { mode: 'system' }, accounts: [] }); const w = mount(MailSettings, { props: { admin: true } }); await flushPromises(); await w.find('select').setValue('mail'); expect(w.find('button').attributes('disabled')).toBeDefined(); expect(w.text()).toContain('Keine eigenen'); w.unmount()
    api.get.mockRejectedValue(new Error('Mail deaktiviert')); const failed = mount(MailSettings, { props: { admin: true } }); await flushPromises(); expect(failed.text()).toContain('Mail deaktiviert'); failed.unmount()
  })
})
