import { describe, it, expect, vi } from 'vitest'
import { reactive } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { serviceLine, lineMinutes, includedWork, berlinInput, berlinIso } from '../../src/services'
import type { ServiceDefinition, ServiceLine } from '../../src/types'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { template: '<button><slot /></button>' } }))
import ServicePicker from '../../src/components/ServicePicker.vue'
import ServiceCatalogue from '../../src/components/ServiceCatalogue.vue'
const oil = { id: 'oil', version: 1, created_at: 0, updated_at: 0, time_min:30, time_max:60, time_source:'https://example.invalid/source', own_price:null, seed_key: 'oil', title: 'Ölwechsel', category: 'Wartung', description: 'Öl und Filter', unit: 'Leistung', vehicle_classes: ['car','van'], synonyms: ['Motoröl'], steps: ['Öl erneuern'], minutes: 45, includes: [], time_kind: 'research', favorite: true, archived: false, position: 1 } as ServiceDefinition
const inspect = { ...oil, id: 'inspect', seed_key: 'inspect', title: 'Inspektion', synonyms: [], minutes: null, includes: ['oil'] }
describe('shared service selection', () => {
 it('uses Berlin time independently and rejects ambiguous or nonexistent DST inputs', () => {
  expect(berlinInput('2026-10-06T06:00:00Z')).toBe('2026-10-06T08:00')
  expect(berlinIso('2026-10-06T08:00')).toBe('2026-10-06T06:00:00.000Z')
  expect(berlinIso('2026-12-06T08:00')).toBe('2026-12-06T07:00:00.000Z')
  expect(() => berlinIso('2026-03-29T02:30')).toThrow('existiert')
  expect(() => berlinIso('2026-10-25T02:30')).toThrow('zweimal')
  expect(berlinIso('2026-10-25T03:30')).toBe('2026-10-25T02:30:00.000Z')
 })
 it('invalidates copied durations when the vehicle class changes', async () => {
  api.get.mockResolvedValue({ items: [oil] }); const w = mount(ServicePicker, { props: { modelValue: [serviceLine(oil)], vehicleClass: 'car' } }); await flushPromises()
  await w.setProps({ vehicleClass: 'van' }); const next = w.emitted('update:modelValue')?.[0][0] as ServiceLine[]; expect(next[0].vehicle_class).toBe('van'); expect(next[0].minutes).toBeNull(); w.unmount()
 })
 it('keeps snapshots independent and requires explicit van duration', () => {
  const line = serviceLine(oil); line.steps.push('Zusatz'); expect(oil.steps).toEqual(['Öl erneuern']); expect(serviceLine(oil,'van').minutes).toBeNull()
  expect(lineMinutes([{...line, quantity:2}])).toBe(90); expect(lineMinutes([line, serviceLine(inspect)])).toBeNull(); expect(includedWork([line,serviceLine(inspect)],[oil,inspect])).toEqual(['Inspektion → Ölwechsel'])
 })
 it('searches synonyms and changes quantity without dropping attached material costs', async () => {
  api.get.mockResolvedValue({items:[oil,inspect]}); const line={...serviceLine(oil),material_euros:12.5, external_euros:4} as ServiceLine
  const w=mount(ServicePicker,{props:{modelValue:[line]}});await flushPromises();await w.find('input[type="search"]').setValue('Motoröl');expect(w.findAll('.wm-service-result')).toHaveLength(1)
  await w.find('.wm-service-line input[type="number"]').setValue(2); const next=w.emitted('update:modelValue')?.[0][0] as ServiceLine[];expect(next[0].quantity).toBe(2);expect(next[0].material_euros).toBe(12.5)
  w.unmount()
 })
 it('clears an optional catalogue price instead of silently saving zero', async () => {
  api.get.mockResolvedValue({ items: [{ ...oil, own_price: 3361 }], groups: [], version: 'test' }); api.post.mockResolvedValue({})
  const w = mount(ServiceCatalogue, { global: { stubs: { Modal: { template: '<div><slot /></div>' } } } }); await flushPromises()
  await w.findAll('button').find(b => b.text().includes('bearbeiten'))!.trigger('click')
  await w.find('input[step="0.01"]').setValue(''); await w.find('form').trigger('submit'); await flushPromises()
  expect(api.post).toHaveBeenLastCalledWith('/api/v1/services', { data: expect.objectContaining({ id: 'oil', own_price: null }) }); w.unmount()
 })
 it('opens a reactive catalogue entry without structuredClone proxy failure', async () => {
  api.get.mockResolvedValue({items:reactive([oil]),groups:[],version:'test'});const w=mount(ServiceCatalogue,{global:{stubs:{Modal:{template:'<div><slot /></div>'}}}});await flushPromises()
  await w.findAll('button').find(b=>b.text().includes('bearbeiten'))!.trigger('click');expect(w.find('input[required]').exists()).toBe(true);w.unmount()
 })
 it('copies free package work without an invalid empty catalogue reference', async () => {
  api.get.mockResolvedValue({ items: [], groups: [] }); api.post.mockResolvedValue({})
  const w = mount(ServiceCatalogue, { global: { stubs: { Modal: { template: '<div><slot /></div>' } } } }); await flushPromises()
  await w.findAll('button').find(b => b.text().includes('anlegen'))!.trigger('click')
  w.findComponent(ServicePicker).vm.$emit('update:modelValue',[{ service_id:'',key:'',title:'Zusatzarbeit',quantity:1,minutes:35,unit:'Leistung',steps:['Freie Diagnose'],vehicle_class:'car' }]); await flushPromises()
  await w.findAll('button').find(b => b.text().includes('Gesamtzeit ins Paket'))!.trigger('click')
  await w.find('form').trigger('submit'); await flushPromises()
  expect(api.post).toHaveBeenLastCalledWith('/api/v1/services', { data: expect.objectContaining({ minutes:35, steps:['1 \u00d7 Zusatzarbeit','Freie Diagnose'], includes:[] }) }); w.unmount()
 })

})
