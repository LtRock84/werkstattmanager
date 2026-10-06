import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { emptyChecklist, followUpWork, freePlanningDays, matchesAppointment } from '../../src/planning'
import { auditEntry } from '../../src/audit'
import type { Appointment, CapacityDay, Vehicle, Audit } from '../../src/types'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
const api = vi.hoisted(() => ({ all: vi.fn(), get: vi.fn(), post: vi.fn(), command: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
vi.mock('../../src/components/Modal.vue', () => ({ default: { props: ['dirty', 'locked'], template: '<div><slot /></div>' } }))
import AppointmentEditor from '../../src/components/AppointmentEditor.vue'
import AppointmentBook from '../../src/components/AppointmentBook.vue'
import ExistingWorkNotice from '../../src/components/ExistingWorkNotice.vue'
const a: Appointment = { id:'a1', version:4, created_at:0, updated_at:0, vehicle_id:'v1', title:'Ölwechsel', customer_email:'old@example.invalid', arrival:'2026-10-07T06:00:00Z', work_start:null, pickup:null, service_lines:[{ service_id:'',key:'',title:'Ölwechsel',minutes:45,quantity:1,steps:['Prüfen'],unit:'Leistung',vehicle_class:'car' }], minutes:45, allocations:[], status:'confirmed', stage:'customer_question', note:'OLD SECRET', override_reason:'OLD REASON', reminder:true, order_id:'o1', checklist:{ ...emptyChecklist(), callback_note:'OLD CALLBACK', key_received:true } }
const day = (date: string, changes: Partial<CapacityDay> = {}): CapacityDay => ({ date, minutes:480, used:0, free:480, percent:0, vehicle_count:0, max_vehicles:null, closed:false, overbooked:false, override:null, ...changes })
beforeEach(() => { vi.resetAllMocks(); api.all.mockResolvedValue([]); api.get.mockResolvedValue({items:[]}); api.command.mockResolvedValue({}) })
describe('workshop reception and planning', () => {
  it('warns about other unfinished vehicle work without duplicating the linked order', async () => {
    api.all.mockResolvedValue([{id:'one',status:'active',title:'Vorhandene Inspektion'},{id:'linked',status:'open',title:'Dieser Auftrag'},{id:'done',status:'completed',title:'Alter Auftrag'}])
    const w=mount(ExistingWorkNotice,{props:{vehicleId:'v1',excludeOrderId:'linked'}}); await flushPromises()
    expect(api.all).toHaveBeenCalledWith('orders',{vehicle_id:'v1'})
    expect(w.text()).toContain('Vorhandene Inspektion'); expect(w.text()).not.toContain('Dieser Auftrag'); expect(w.text()).not.toContain('Alter Auftrag')
    expect(api.command).not.toHaveBeenCalled(); w.unmount()
  })
  it('copies only work and keeps original steps immutable', () => {
    const copy = followUpWork(a)
    expect(Object.keys(copy).sort()).toEqual(['minutes','service_lines','title','vehicle_id'])
    copy.service_lines[0].steps.push('Neu'); expect(a.service_lines[0].steps).toEqual(['Prüfen'])
  })
  it('never offers closed overbooked full or insufficient days', () => {
    const days = [day('2026-10-06'),day('2026-10-07',{closed:true}),day('2026-10-08',{free:44}),day('2026-10-09',{max_vehicles:1,vehicle_count:1}),day('2026-10-10',{overbooked:true}),day('2026-10-11',{free:45,max_vehicles:2,vehicle_count:1})]
    expect(freePlanningDays(days,45).map(d=>d.date)).toEqual(['2026-10-06','2026-10-11'])
    expect(freePlanningDays(days,null)).toEqual([])
  })
  it('searches every word case-insensitively and keeps cancelled appointments out of waiting', () => {
    const vehicle = { plate:'B-TEST 1', vin:'WVW123', number:'Flotte 5' } as Vehicle
    expect(matchesAppointment(a,vehicle,'b-test öl','waiting')).toBe(true)
    expect(matchesAppointment(a,vehicle,'wvw123','confirmed')).toBe(true)
    expect(matchesAppointment({...a,status:'cancelled'},vehicle,'','waiting')).toBe(false)
    expect(matchesAppointment(a,vehicle,'nicht vorhanden','all')).toBe(false)
  })
  it('prepares a follow-up without saving historical notes checkboxes address or links', async () => {
    api.all.mockImplementation(async (kind:string) => kind === 'vehicles' ? [{id:'v1',customer_id:'c1'}] : kind === 'customers' ? [{id:'c1',email:'current@example.invalid'}] : [])
    const w = mount(AppointmentEditor,{props:{copyFrom:a,day:'2026-10-09'}}); await flushPromises()
    expect(w.text()).toContain('Bisher wurde nichts gespeichert')
    expect(api.command).not.toHaveBeenCalled()
    expect(w.find('input[type="email"]').element).toHaveProperty('value','current@example.invalid')
    await w.findAll('button').find(b=>b.text()==='Entwurf speichern')!.trigger('click'); await flushPromises()
    const payload=api.command.mock.calls[0][1]
    expect(payload).toMatchObject({id:undefined,version:undefined,status:'draft',arrival:'2026-10-09T06:00:00.000Z',pickup:null,work_start:null,note:'',override_reason:'',reminder:false,checklist:emptyChecklist()})
    expect(payload).not.toHaveProperty('order_id'); w.unmount()
  })
  it('saves checklist explicitly and explains its changes without technical keys', async () => {
    const w=mount(AppointmentEditor,{props:{appointment:a,day:'2026-10-07'}}); await flushPromises()
    const checks=w.findAll('input[type="checkbox"]'); await checks.find(c=>c.element.parentElement?.textContent?.includes('Abschlusskontrolle'))!.setValue(true)
    await w.find('form').trigger('submit'); await flushPromises()
    expect(api.command.mock.calls[0][1].checklist).toMatchObject({key_received:true,final_checked:true,callback_note:'OLD CALLBACK'})
    const readable=auditEntry({id:'audit1',version:1,created_at:0,updated_at:0,order_id:null,employee_id:null,action:'appointment.confirmed',actor:'meister',details:{checklist_changes:[{field:'key_received',before:false,after:true},{field:'callback_note',before:'',after:'Bitte zurückrufen'}]}} as Audit)
    expect(readable.lines).toContainEqual({label:'Fahrzeugschlüssel entgegengenommen',value:'Nicht bestätigt → Erledigt'})
    expect(JSON.stringify(readable)).not.toContain('key_received'); w.unmount()
  })
  it('shows suggestions and requires explicit choice without reserving capacity', async () => {
    api.get.mockImplementation(async (url:string) => url.includes('/appointments?') ? {items:[],days:[day('2026-10-09')]} : {items:[]})
    const w=mount(AppointmentEditor,{props:{copyFrom:a,day:'2026-10-07'}}); await flushPromises()
    await w.findAll('button').find(b=>b.text()==='Passende freie Tage finden')!.trigger('click'); await flushPromises()
    expect(api.command).not.toHaveBeenCalled()
    await w.findAll('button').find(b=>b.text().includes('09.10.2026'))!.trigger('click')
    expect(w.find('input[type="datetime-local"]').element).toHaveProperty('value','2026-10-09T08:00'); w.unmount()
  })
  it('filters cards while leaving full day capacity unchanged', async () => {
    api.get.mockImplementation(async(url:string)=>url.includes('/appointments?')?{items:[a,{...a,id:'a2',title:'Bremsen',stage:'ready'}],days:[day('2026-10-07',{used:90,free:390})],settings:{settings:{weekdays:{}}}}:{items:[]})
    const w=mount(AppointmentBook); await flushPromises()
    await w.find('input[type="search"]').setValue('Bremsen'); expect(w.findAll('.wm-appointment')).toHaveLength(1)
    expect(w.text()).toContain('1,5 von 8 Stunden verplant'); w.unmount()
  })
  it('shows the saved appointment day and clears filters that would hide the result', async () => {
    api.get.mockImplementation(async(url:string)=>url.includes('/appointments?')?{items:[],days:[],settings:{settings:{weekdays:{}}}}:{items:[]})
    const w=mount(AppointmentBook); await flushPromises()
    await w.find('input[type="search"]').setValue('old search')
    await w.findAll('button').find(b=>b.text().includes('Kundentermin anlegen'))!.trigger('click')
    w.findComponent(AppointmentEditor).vm.$emit('saved',{...a,arrival:'2026-11-10T07:00:00Z',status:'draft'}); await flushPromises()
    expect(w.find('input[type="date"]').element).toHaveProperty('value','2026-11-10')
    expect(w.find('input[type="search"]').element).toHaveProperty('value','')
    expect(w.text()).toContain('Keine Kapazität gebucht und keine Nachricht versendet.'); w.unmount()
  })
})
