import { t } from './format'

/** Prepare photos locally; the server retains its independent upload limits. */
export async function prepareDriverPhoto(file: File): Promise<File> {
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) throw new Error(t('Bitte ein JPEG-, PNG- oder WebP-Bild auswählen.'))
  const address = URL.createObjectURL(file)
  try {
    const image = new Image()
    await new Promise<void>((resolve, reject) => { image.onload = () => resolve(); image.onerror = () => reject(new Error(t('Das Foto konnte nicht geöffnet werden.'))); image.src = address })
    const width = image.naturalWidth, height = image.naturalHeight
    if (!width || !height) throw new Error(t('Das Foto konnte nicht geöffnet werden.'))
    if (width * height <= 12000000 && file.size <= 8 * 1024 * 1024) return file
    const scale = Math.min(1, Math.sqrt(10000000 / (width * height)), 3000 / Math.max(width, height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.floor(width * scale)); canvas.height = Math.max(1, Math.floor(height * scale))
    const context = canvas.getContext('2d')
    if (!context) throw new Error(t('Das Foto konnte nicht verkleinert werden.'))
    context.drawImage(image, 0, 0, canvas.width, canvas.height)
    const blob = await new Promise<Blob | null>(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.82))
    if (!blob || blob.size > 8 * 1024 * 1024) throw new Error(t('Das Foto konnte nicht ausreichend verkleinert werden.'))
    return new File([blob], file.name.replace(/\.[^.]*$/, '') + '.jpg', { type: 'image/jpeg' })
  } finally { URL.revokeObjectURL(address) }
}
