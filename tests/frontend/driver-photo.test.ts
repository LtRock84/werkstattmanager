import { afterEach, describe, expect, it, vi } from 'vitest'
vi.mock('@nextcloud/l10n', () => ({ translate: (_: string, s: string) => s }))
import { prepareDriverPhoto } from '../../src/driver-photo'

afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals() })
describe('driver photo preparation', () => {
  it('resizes a photo above the server pixel limit before upload', async () => {
    const revoke = vi.fn(), draw = vi.fn()
    vi.stubGlobal('URL', { createObjectURL: () => 'blob:test', revokeObjectURL: revoke })
    vi.stubGlobal('Image', class { naturalWidth = 6000; naturalHeight = 4000; onload: (() => void) | null = null; set src(_value: string) { queueMicrotask(() => this.onload?.()) } })
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({ drawImage: draw } as unknown as CanvasRenderingContext2D)
    vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation(callback => callback(new Blob(['compressed'], { type: 'image/jpeg' })))
    const result = await prepareDriverPhoto(new File(['original'], 'photo.png', { type: 'image/png' }))
    expect(result.type).toBe('image/jpeg')
    expect(result.name).toBe('photo.jpg')
    expect(draw.mock.calls[0][3] * draw.mock.calls[0][4]).toBeLessThanOrEqual(12000000)
    expect(revoke).toHaveBeenCalledWith('blob:test')
  })
})
