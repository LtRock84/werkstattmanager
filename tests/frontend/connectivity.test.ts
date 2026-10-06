import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
const transport = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn() }))
vi.mock('@nextcloud/axios', () => ({
  default: transport,
  isAxiosError: (e: { isAxiosError?: boolean }) => e?.isAxiosError === true,
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path: string) => path }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, text: string) => text }))
import { command, post, put } from '../../src/api'
beforeEach(() => vi.clearAllMocks())
afterEach(() => vi.unstubAllGlobals())
describe('server-confirmed connectivity', () => {
  it('sends and safely retries a workshop command on a local HTTP origin', async () => {
    vi.stubGlobal('crypto', { getRandomValues: crypto.getRandomValues.bind(crypto) })
    const failure = Object.assign(new Error('Response lost'), { isAxiosError: true })
    transport.post.mockRejectedValueOnce(failure).mockResolvedValueOnce({ data: { status: 'active' } })
    const payload = { version: 1, employee_id: 'employee-http' }
    await expect(command('/http/start', payload)).rejects.toBe(failure)
    await expect(command('/http/start', payload)).resolves.toEqual({ status: 'active' })
    expect(transport.post.mock.calls[0][1].data.request_id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/)
    expect(transport.post.mock.calls[1][1]).toEqual(transport.post.mock.calls[0][1])
  })
  it('contacts a reachable Nextcloud despite an offline browser hint', async () => {
    const hint = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)
    try {
      transport.post.mockResolvedValue({ data: { status: 'active' } })
      transport.put.mockResolvedValue({ data: { saved: true } })
      await expect(post('/start', { data: {} })).resolves.toEqual({ status: 'active' })
      await expect(put('/settings', { data: {} })).resolves.toEqual({ saved: true })
    } finally { hint.mockRestore() }
  })
  it('never confirms a booking when transport fails and reuses its request ID on explicit retry', async () => {
    const failure = Object.assign(new Error('Network unavailable'), { isAxiosError: true })
    transport.post.mockRejectedValueOnce(failure).mockResolvedValueOnce({ data: { status: 'active' } })
    const payload = { version: 1, employee_id: 'employee-a' }
    await expect(command('/api/v1/orders/test-connectivity/start', payload)).rejects.toBe(failure)
    await expect(command('/api/v1/orders/test-connectivity/start', payload)).resolves.toEqual({ status: 'active' })
    expect(transport.post).toHaveBeenCalledTimes(2)
    expect(transport.post.mock.calls[1][1]).toEqual(transport.post.mock.calls[0][1])
    expect(transport.post.mock.calls[0][1].data.request_id).toEqual(expect.any(String))
  })
})
