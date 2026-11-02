import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, text: string) => text }))
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('../../src/components/CompanyHeader.vue', () => ({ default: { template: '<div>Werkstatt</div>' } }))
import PickupPass from '../../src/components/PickupPass.vue'

const view = { customer_name: 'Erika <Beispiel>', vehicle: { plate: 'B-AB 123', number: '', make: 'VW', model: 'Golf' }, ready: false }
beforeEach(() => { vi.resetAllMocks(); history.replaceState(null, '', '/abholung'); api.get.mockResolvedValue(view); api.post.mockResolvedValue(view) })

describe('public pickup pass', () => {
  it('exchanges the fragment once, clears it and exposes only the reduced receipt', async () => {
    const token = 'a'.repeat(64)
    history.replaceState(null, '', '/abholung#' + token)
    const wrapper = mount(PickupPass); await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/public/v1/pickup/session', { token })
    expect(location.hash).toBe('')
    expect(wrapper.text()).toContain('Erika <Beispiel>')
    expect(wrapper.text()).toContain('noch nicht zur Abholung freigegeben')
    expect(wrapper.find('script').exists()).toBe(false)
  })

  it('clearly shows the ready state and does not offer internal work details', async () => {
    api.get.mockResolvedValue({ ...view, ready: true })
    const wrapper = mount(PickupPass); await flushPromises()
    expect(wrapper.find('.wm-pickup-ready').text()).toContain('abholbereit')
    expect(wrapper.text()).not.toContain('Preis')
    expect(wrapper.text()).not.toContain('Arbeitszeit')
    expect(wrapper.text()).toContain('keine technische Freigabe')
  })

  it('shows no vehicle data after an invalid or revoked session', async () => {
    api.get.mockRejectedValue(new Error('Abholcode ungültig oder nicht mehr aktiv.'))
    const wrapper = mount(PickupPass); await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toContain('ungültig')
    expect(wrapper.text()).not.toContain('B-AB 123')
  })
})
