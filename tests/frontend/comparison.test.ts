import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { comparisonPrice } from '../../src/services'
import type { PriceGroup, ServiceDefinition, ServiceLine } from '../../src/types'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
vi.mock('../../src/components/ServicePicker.vue', () => ({ default: { name: 'ServicePicker', props: ['modelValue'], emits: ['update:modelValue'], template: '<div />' } }))
import PriceComparison from '../../src/components/PriceComparison.vue'
import PriceCalculator from '../../src/components/PriceCalculator.vue'
import ServicePicker from '../../src/components/ServicePicker.vue'
const group = { key: 'test', scope: 'Vier Räder', material: 'ohne Material', unit: 'Satz', vehicle_class: 'car', region: 'Berlin/Brandenburg', count: 5, mean: 11900, median: 11900, quality: 'comparison', price_kind: 'fixed', observations: Array.from({ length: 5 }, (_, i) => ({ id: String(i), vat: 19 })) } as PriceGroup
beforeEach(() => {
  vi.resetAllMocks()
  api.get.mockImplementation(async (url: string) => url === '/api/v1/pricing' ? { profile: null } : url === '/api/v1/services' ? { items: [{ id: 's1', version: 2, own_price: null } as ServiceDefinition] } : { groups: [group], items: [] })
  api.post.mockResolvedValue({})
})
describe('explicit regional benchmark adoption', () => {
  it('converts known source VAT and refuses unsupported or mixed bases', () => {
    expect(comparisonPrice(group)?.net_cents).toBe(10000)
    expect(comparisonPrice({ ...group, count: 4, mean: null })).toBeNull()
    expect(comparisonPrice({ ...group, observations: [{ vat: null }] as PriceGroup['observations'] })).toBeNull()
    expect(comparisonPrice({ ...group, observations: [{ vat: 19 }, { vat: 7 }] as PriceGroup['observations'] })).toBeNull()
  })
  it('requires explicit scope/material/unit confirmation and hides incompatible vehicle class', async () => {
    const w = mount(PriceComparison, { props: { serviceKey: 'wheels', selectable: true, vehicleClass: 'car' } }); await flushPromises()
    expect(w.find('button').attributes('disabled')).toBeDefined()
    await w.find('input[type="checkbox"]').setValue(true); await w.find('button').trigger('click')
    expect(w.emitted('select')?.[0]).toEqual([group])
    await w.setProps({ vehicleClass: 'van' }); expect(w.find('button').exists()).toBe(false); w.unmount()
  })
  it('keeps calculator costs intact and stores net target per unit independently of quantity', async () => {
    const w = mount(PriceCalculator); await flushPromises()
    const line = { service_id: 's1', key: 'wheels', title: 'Räder', vehicle_class: 'car', quantity: 2, minutes: 40, unit: 'Leistung', steps: [], material_euros: 12 } as ServiceLine
    w.findComponent(ServicePicker).vm.$emit('update:modelValue', [line]); await flushPromises()
    w.findComponent(PriceComparison).vm.$emit('select', group); await flushPromises()
    expect(w.text()).toContain('200,00')
    expect(line.material_euros).toBe(12)
    expect(api.post).not.toHaveBeenCalled()
    await w.findAll('button').find(b => b.text() === 'Zielpreis ausdrücklich in Katalog speichern')!.trigger('click'); await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/v1/services', { data: expect.objectContaining({ id: 's1', version: 2, own_price: 10000 }) }); w.unmount()
  })
  it('discards a delayed result when the selected work changes while calculating', async () => {
    let resolve!: (value: unknown) => void
    api.post.mockImplementation(() => new Promise(r => { resolve = r }))
    const w = mount(PriceCalculator); await flushPromises()
    const line = { service_id:'s1', key:'wheels', title:'Wheels', vehicle_class:'car', quantity:1, minutes:40, unit:'Leistung', steps:[] } as ServiceLine
    w.findComponent(ServicePicker).vm.$emit('update:modelValue',[line]); await flushPromises()
    await w.findAll('button').find(b => b.text() === 'Preise berechnen')!.trigger('click')
    w.findComponent(ServicePicker).vm.$emit('update:modelValue',[{ ...line, quantity:2 }]); await flushPromises()
    resolve({ cost_rate_cents:8000,rate_cents:9600,net_cents:6400,vat_cents:1216,gross_cents:7616,lines:[{ net_cents:6400 }] }); await flushPromises()
    expect(w.text()).toContain('Bitte erneut berechnen')
    expect(w.text()).not.toContain('Gesamter Leistungspreis brutto'); w.unmount()
  })

})
