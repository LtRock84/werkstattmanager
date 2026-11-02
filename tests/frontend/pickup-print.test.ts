import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { Order, Vehicle } from '../../src/types'
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, s: string) => s }))
const api = vi.hoisted(() => ({ all: vi.fn(), get: vi.fn(), command: vi.fn(), post: vi.fn(), upload: vi.fn() }))
const qr = vi.hoisted(() => ({ toDataURL: vi.fn() }))
vi.mock('../../src/api', () => ({ ...api, message: (e: Error) => e.message }))
vi.mock('qrcode', () => ({ default: qr }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { props: { disabled: Boolean }, template: '<button type="button" :disabled="disabled"><slot /></button>' } }))
vi.mock('../../src/components/Modal.vue', () => ({ default: { template: '<section><slot /></section>' } }))
import { pickupPrintHtml } from '../../src/pickup-print'
import OrderDetail from '../../src/components/OrderDetail.vue'

beforeEach(() => { vi.resetAllMocks(); api.all.mockResolvedValue([]); qr.toDataURL.mockResolvedValue('data:image/png;base64,YQ==') })

describe('pickup print document', () => {
  it('prints the editable text as plain text and keeps the QR image', () => {
    const text = pickupPrintHtml('Abholung <Test>', 'Hallo Erika\nBitte <script>alert(1)</script> vorzeigen', 'data:image/png;base64,YQ==', 'QR-Code')
    const page = new DOMParser().parseFromString(text, 'text/html')
    expect(page.querySelector('h1')?.textContent).toBe('Abholung <Test>')
    expect(page.querySelector('p')?.textContent).toContain('Hallo Erika\nBitte <script>')
    expect(page.querySelector('script')).toBeNull()
    expect(page.querySelector('img')?.getAttribute('src')).toBe('data:image/png;base64,YQ==')
  })
  it('fetches the latest template before opening the print dialog', async () => {
    const order = { id: 'o1', vehicle_id: 'v1', title: 'Test', status: 'open', steps: [], version: 1 } as unknown as Order
    const vehicle = { id: 'v1', plate: 'TEST 1', archived: false } as Vehicle
    const pass = { url: 'https://example.invalid/#token', customer_name: 'Erika', print_title: 'Alt', print_body: 'Alter Text' }
    api.get.mockResolvedValueOnce(pass).mockResolvedValueOnce({ ...pass, print_title: 'Neuer Titel', print_body: 'Neuer Text' })
    const open = vi.fn(), write = vi.fn(), close = vi.fn(), listener = vi.fn()
    const popup = { opener: window, document: { open, write, close }, addEventListener: listener, focus: vi.fn(), print: vi.fn(), close: vi.fn() }
    vi.spyOn(window, 'open').mockReturnValue(popup as unknown as Window)
    const w = mount(OrderDetail, { props: { order, vehicle, employees: [], master: true } }); await flushPromises()
    await w.findAll('button').find(b => b.text() === 'Abholschein drucken')!.trigger('click'); await flushPromises()
    expect(api.get).toHaveBeenCalledTimes(2)
    expect(write.mock.calls[0][0]).toContain('Neuer Titel')
    expect(write.mock.calls[0][0]).toContain('Neuer Text')
    expect(listener).toHaveBeenCalledWith('load', expect.any(Function), { once: true })
    expect(open.mock.invocationCallOrder[0]).toBeLessThan(listener.mock.invocationCallOrder[0])
    expect(listener.mock.invocationCallOrder[0]).toBeLessThan(close.mock.invocationCallOrder[0])
    expect(popup.opener).toBeNull()
    w.unmount(); vi.restoreAllMocks()
  })
})
