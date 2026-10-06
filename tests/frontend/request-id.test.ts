import { afterEach, describe, expect, it, vi } from 'vitest'
import { requestId } from '../../src/request-id'

afterEach(() => vi.unstubAllGlobals())
describe('request IDs on local HTTP origins', () => {
  it('uses the native implementation when available', () => {
    const native = vi.fn(() => '12345678-1234-4123-8123-123456789abc')
    vi.stubGlobal('crypto', { randomUUID: native })
    expect(requestId()).toBe('12345678-1234-4123-8123-123456789abc')
    expect(native).toHaveBeenCalledOnce()
  })
  it('uses cryptographic bytes with UUID version and variant bits without randomUUID', () => {
    const random = vi.fn((bytes: Uint8Array) => { bytes.fill(255); return bytes })
    vi.stubGlobal('crypto', { getRandomValues: random })
    expect(requestId()).toBe('ffffffff-ffff-4fff-bfff-ffffffffffff')
    expect(random).toHaveBeenCalledOnce()
    expect(random.mock.calls[0][0]).toHaveLength(16)
  })
  it('does not fall back to weak randomness when cryptographic randomness fails', () => {
    vi.stubGlobal('crypto', { getRandomValues: () => { throw new Error('No entropy') } })
    expect(requestId).toThrow('No entropy')
  })
})
