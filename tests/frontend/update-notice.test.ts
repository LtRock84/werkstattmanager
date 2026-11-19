import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, text: string) => text }))
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: ['disabled'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' } }))
import UpdateNotice from '../../src/components/UpdateNotice.vue'
beforeEach(() => {
  vi.resetAllMocks()
  HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', '') }
  HTMLDialogElement.prototype.close = function () { this.removeAttribute('open') }
})
describe('update notice', () => {
  it('requires a successful confirmation before dismissing the current version', async () => {
    api.get.mockResolvedValue({ version: '0.6.2', pending: true })
    api.post.mockRejectedValueOnce(new Error('Offline')).mockResolvedValueOnce({ pending: false })
    const wrapper = mount(UpdateNotice); await flushPromises()
    expect(wrapper.text()).toContain('Neue Funktionen')
    await wrapper.find('button').trigger('click'); await flushPromises()
    expect(wrapper.text()).toContain('Offline'); expect(wrapper.find('dialog').exists()).toBe(true)
    await wrapper.find('button').trigger('click'); await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/v1/update-notice', { version: '0.6.2' })
    expect(wrapper.find('dialog').exists()).toBe(false); wrapper.unmount()
  })
  it('does not show the dialog after this user has confirmed', async () => {
    api.get.mockResolvedValue({ version: '0.6.2', pending: false })
    const wrapper = mount(UpdateNotice); await flushPromises()
    expect(wrapper.find('dialog').exists()).toBe(false); wrapper.unmount()
  })
})
