import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
const api = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' } }))
import PriceComparison from '../../src/components/PriceComparison.vue'
const observation = { id: 'p1', provider: 'Legacy external company', source: 'https://example.invalid/supplier', city: 'Berlin', gross_cents: 11900, checked_date: '2026-10-05', price_kind: 'fixed', scope: 'Vier Räder', eligible: true, exclusion: '' }
const group = { key: 'g', count: 5, local_count: 5, quality: 'comparison', mean: 11900, median: 11900, min: 10000, max: 13000, vat: 19, scope: 'Vier Räder', unit: 'Satz', material: 'Kein Material erforderlich', region: 'Berlin/Brandenburg', vehicle_class: 'car', segment: 'freie Werkstatt', price_kind: 'fixed', undated: true, observations: [{ ...observation, vat: 19 }] }
describe('comparison prices without supplier publication', () => {
  it('shows price details but no company names or links, including excluded observations', async () => {
    api.get.mockResolvedValue({ groups: [group], items: [{ ...observation, eligible: false, exclusion: 'Umfang unklar' }] })
    const w = mount(PriceComparison, { props: { serviceKey: 'wheels' } }); await flushPromises()
    expect(w.text()).toContain('119,00'); expect(w.text()).toContain('2026-10-05')
    expect(w.text()).not.toContain('Legacy external company'); expect(w.findAll('a')).toHaveLength(0)
    expect(w.text()).toContain('5 unabhängige Anbieter'); w.unmount()
  })
  it('keeps explicit confirmation before adopting the unchanged comparison', async () => {
    api.get.mockResolvedValue({ groups: [group], items: [observation] })
    const w = mount(PriceComparison, { props: { serviceKey: 'wheels', selectable: true, vehicleClass: 'car' } }); await flushPromises()
    const button = w.find('button'); expect(button.attributes('disabled')).toBeDefined()
    await w.find('input[type="checkbox"]').setValue(true); await button.trigger('click')
    expect(w.emitted('select')?.[0][0]).toEqual(group); w.unmount()
  })
})
