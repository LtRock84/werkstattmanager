import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, s: string) => s }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: () => 'master' }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    props: { disabled: Boolean },
    template: '<button type="button" :disabled="disabled"><slot /></button>',
  },
}))
const api = vi.hoisted(() => ({
  all: vi.fn(),
  list: vi.fn(),
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  command: vi.fn(),
  upload: vi.fn(),
}))
vi.mock('../../src/api', () => ({
  ...api,
  url: (path: string) => path,
  message: (e: Error) => e.message,
}))
import App from '../../src/App.vue'
import VehicleDetail from '../../src/components/VehicleDetail.vue'
import type { Vehicle } from '../../src/types'
const vehicle = {
  id: 'v1',
  plate: 'TEST 1',
  kind: 'fleet',
  mileage: 1000,
  archived: false,
} as Vehicle
const rows: Record<string, unknown[]> = {
  vehicles: [vehicle],
  employees: [],
  active: [],
  orders: [
    { id: 'o1', vehicle_id: 'v1', title: 'Inspektion', status: 'open', steps: [], version: 1 },
  ],
  defects: [
    { id: 'd1', vehicle_id: 'v1', description: 'Kontrollleuchte', status: 'new', version: 1 },
  ],
}
beforeEach(() => {
  vi.resetAllMocks()
  HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
    this.setAttribute('open', '')
    this.querySelector('button')?.focus()
  }
  HTMLDialogElement.prototype.close = function (this: HTMLDialogElement) {
    this.removeAttribute('open')
  }
  api.all.mockImplementation(async (kind: string) => rows[kind] ?? [])
  api.list.mockImplementation(async (kind: string) => ({
    items: rows[kind] ?? [],
    more: false,
    offset: 0,
  }))
})
describe('vehicle sections and returning from details', () => {
  it('shows a real connection failure and recovers through refresh', async () => {
    api.list.mockRejectedValueOnce(Object.assign(new Error('No response'), { isAxiosError: true }))
    const w = mount(App)
    try {
      await flushPromises()
      expect(w.text()).toContain('Keine Verbindung.')
      expect(w.findAll('button').find(b => b.text() === '+ Neuer Auftrag')!.attributes('disabled')).toBeDefined()
      await w.findAll('button').find(b => b.text() === 'Aktualisieren')!.trigger('click')
      await flushPromises()
      expect(w.text()).toContain('Verbunden')
      expect(w.findAll('button').find(b => b.text() === '+ Neuer Auftrag')!.attributes('disabled')).toBeUndefined()
    } finally { w.unmount() }
  })
  it('enables actions after a real server response despite an offline browser hint', async () => {
    const hint = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)
    const w = mount(App)
    try {
      await flushPromises()
      expect(w.text()).toContain('Verbunden')
      expect(w.findAll('button').find(b => b.text() === '+ Neuer Auftrag')!.attributes('disabled')).toBeUndefined()
      window.dispatchEvent(new Event('offline'))
      await flushPromises()
      expect(w.text()).toContain('Verbunden')
    } finally { w.unmount(); hint.mockRestore() }
  })
  for (const [section, detail] of [
    ['Aufträge', 'Inspektion'],
    ['Mängel', 'Prüfen / bearbeiten'],
  ]) {
    it(`returns from ${section} to the selected vehicle section`, async () => {
      const w = mount(App, { attachTo: document.body })
      try {
        await flushPromises()
        await w
          .findAll('button')
          .find((b) => b.text() === 'Fahrzeuge')!
          .trigger('click')
        await flushPromises()
        await w.find('.wm-order-card').trigger('click')
        await flushPromises()
        const record = w.findComponent(VehicleDetail)
        await record
          .findAll('button')
          .find((b) => b.text() === section)!
          .trigger('click')
        const opener = record.findAll('button').find((b) => b.text().includes(detail))!
        ;(opener.element as HTMLButtonElement).focus()
        await opener.trigger('click')
        await flushPromises()
        expect(record.props('active')).toBe(false)
        expect(record.find('dialog').attributes('open')).toBeUndefined()
        await w
          .findAll('button')
          .find((b) => b.text() === 'Zurück zur Fahrzeugakte')!
          .trigger('click')
        await flushPromises()
        expect(record.props('active')).toBe(true)
        expect(record.find('dialog').attributes('open')).toBeDefined()
        expect(record.find('nav button[aria-pressed="true"]').text()).toBe(section)
        expect(document.activeElement).toBe(opener.element)
        expect(api.all).toHaveBeenCalledWith('vehicles', { id: 'v1' })
        await record
          .findAll('button')
          .find((b) => b.text() === 'Historie')!
          .trigger('click')
        expect(record.text()).toContain('Änderungsprotokoll')
      } finally {
        w.unmount()
      }
    })
  }
  it('keeps completion date and mileage editable in the deadlines section', async () => {
    const w = mount(VehicleDetail, { props: { vehicle } })
    try {
      await flushPromises()
      await w.findAll('nav button').find(b => b.text() === 'Termine')!.trigger('click')
      await w.find('input[type="number"]').setValue('1200')
      await w.find('input[type="date"]').setValue('2026-10-05')
      expect((w.find('input[type="number"]').element as HTMLInputElement).value).toBe('1200')
      expect((w.find('input[type="date"]').element as HTMLInputElement).value).toBe('2026-10-05')
    } finally { w.unmount() }
  })

})
