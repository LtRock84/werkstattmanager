import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), upload: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
import DriverForm from '../../src/components/DriverForm.vue'
const view = { vehicle: { plate: 'TEST-QR', number: '', make: '', model: '' }, items: [], more: false }
beforeEach(() => {
  vi.resetAllMocks()
  history.replaceState(null, '', '/fahrer')
  api.get.mockResolvedValue(view)
  api.post.mockResolvedValue({ id: 'defect1', received: true })
  api.upload.mockResolvedValue({ id: 'photo1' })
})
async function form() { const w = mount(DriverForm); await flushPromises(); return w }
afterEach(() => vi.unstubAllGlobals())
async function photo(w: Awaited<ReturnType<typeof form>>, count = 1) {
  Object.defineProperty(w.find('input[type="file"]').element, 'files', {
    configurable: true, value: Array.from({ length: count }, (_, n) => new File(['test'], `photo${n}.png`, { type: 'image/png' })),
  })
  await w.find('input[type="file"]').trigger('change')
}
describe('anonymous driver workflow', () => {
  it('opens and submits a report without randomUUID on local HTTP', async () => {
    vi.stubGlobal('crypto', { getRandomValues: crypto.getRandomValues.bind(crypto) })
    const w = await form()
    await w.find('textarea').setValue('Lokale WLAN-Meldung')
    await w.find('form').trigger('submit'); await flushPromises()
    expect(api.post.mock.calls[0][1].data.request_id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/)
    expect(w.find('[role="status"]').text()).toContain('eingegangen')
    w.unmount()
  })
  it('exchanges the fragment token and removes it from the visible address', async () => {
    const token = 'a'.repeat(64)
    history.replaceState(null, '', '/fahrer#' + token)
    api.post.mockResolvedValue(view)
    const w = await form()
    expect(api.post).toHaveBeenCalledWith('/public/v1/session', { token })
    expect(location.hash).toBe('')
    expect(w.text()).toContain('TEST-QR'); w.unmount()
  })
  it('does not offer a report form when the session is revoked or missing', async () => {
    api.get.mockRejectedValue(new Error('Fahrzeuglink wurde widerrufen.'))
    const w = await form()
    expect(w.find('form').exists()).toBe(false)
    expect(w.find('[role="alert"]').text()).toContain('widerrufen'); w.unmount()
  })
  it('submits mileage with the defect and clears inputs only after receipt', async () => {
    const w = await form()
    await w.find('textarea').setValue('Rückleuchte defekt')
    await w.find('input[type="number"]').setValue('10123')
    await w.find('input[autocomplete="name"]').setValue('Testfahrer')
    await w.find('form').trigger('submit'); await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/public/v1/defects', { data: expect.objectContaining({ description: 'Rückleuchte defekt', mileage: 10123, reporter: 'Testfahrer' }) })
    expect(w.find('[role="status"]').text()).toContain('eingegangen')
    expect(w.find('textarea').element.value).toBe('')
    expect(w.find('input[type="number"]').element).toHaveProperty('value', ''); w.unmount()
  })
  it('retries a lost report response with the same request id and unchanged inputs', async () => {
    api.post.mockRejectedValueOnce(new Error('Antwort verloren')).mockResolvedValueOnce({ id: 'defect1' })
    const w = await form()
    await w.find('textarea').setValue('Motorgeräusch')
    await w.find('form').trigger('submit'); await flushPromises()
    expect(w.find('textarea').element.value).toBe('Motorgeräusch')
    await w.find('form').trigger('submit'); await flushPromises()
    expect(api.post.mock.calls[0]).toEqual(api.post.mock.calls[1]); w.unmount()
  })
  it('retries a failed photo without creating a second defect', async () => {
    api.upload.mockRejectedValueOnce(new Error('Fotoverbindung unterbrochen')).mockResolvedValueOnce({ id: 'photo1' })
    const w = await form()
    await w.find('textarea').setValue('Rückleuchte defekt'); await photo(w)
    await w.find('form').trigger('submit'); await flushPromises()
    expect(w.find('[role="alert"]').text()).toContain('Meldung gespeichert')
    expect(w.find('fieldset').attributes()).toHaveProperty('disabled')
    expect(w.text()).toContain('Fotos erneut senden')
    await w.find('form').trigger('submit'); await flushPromises()
    expect(api.post).toHaveBeenCalledTimes(1)
    expect(api.upload).toHaveBeenCalledTimes(2)
    expect(api.upload.mock.calls[0]).toEqual(api.upload.mock.calls[1])
    expect(w.find('[role="status"]').text()).toContain('eingegangen'); w.unmount()
  })
  it('clears the native photo chooser only after successful delivery', async () => {
    const w = await form()
    await w.find('textarea').setValue('Test'); await photo(w)
    const input = w.find('input[type="file"]').element
    let selected = 'photo0.png'
    Object.defineProperty(input, 'value', { configurable: true, get: () => selected, set: (value: string) => { selected = value } })
    api.upload.mockRejectedValueOnce(new Error('Offline')).mockResolvedValueOnce({ id: 'photo1' })
    await w.find('form').trigger('submit'); await flushPromises()
    expect(selected).toBe('photo0.png')
    await w.find('form').trigger('submit'); await flushPromises()
    expect(selected).toBe('')
    expect(api.post).toHaveBeenCalledTimes(1); w.unmount()
  })
  it('rejects six photos before sending the report', async () => {
    const w = await form()
    await w.find('textarea').setValue('Test'); await photo(w, 6)
    await w.find('form').trigger('submit'); await flushPromises()
    expect(api.post).not.toHaveBeenCalled(); expect(api.upload).not.toHaveBeenCalled()
    expect(w.find('[role="alert"]').text()).toContain('Maximal fünf'); w.unmount()
  })
  it('renders published text as text rather than executable HTML', async () => {
    api.get.mockResolvedValue({ ...view, items: [{ text: '<script>alert(1)</script>', created_at: 0 }] })
    const w = await form()
    expect(w.text()).toContain('<script>alert(1)</script>')
    expect(w.find('script').exists()).toBe(false); w.unmount()
  })
})
