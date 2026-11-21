export const TEXT_PREVIEW_BYTES = 256 * 1024
export const MAX_AVATAR_BYTES = 5 * 1024 * 1024
export const MAX_COMMENT_FILES = 5
export const MAX_COMMENT_BYTES = 50 * 1024 * 1024

export const MEDIA_ACCEPT =
    'image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain,text/csv,text/markdown,application/json'
export const AVATAR_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif'

const IMAGE_MIME_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
const TEXT_MIME_TYPES = new Set(['text/plain', 'text/csv', 'text/markdown', 'application/json'])

/** Same quality the server uses for its WebP thumbnails (MediaPolicy::WEBP_QUALITY). */
export const WEBP_QUALITY = 0.82

function timestampName(): string {
    const now = new Date()
    const pad = (n: number): string => String(n).padStart(2, '0')
    return `screenshot-${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`
}

/**
 * Re-encodes a pasted image (usually a large PNG screenshot) to WebP. GIFs keep their
 * animation and are returned as is; so is the original when the browser cannot encode
 * WebP (older Safari silently falls back to PNG).
 */
export async function pastedImageToWebp(image: Blob): Promise<File> {
    const original = new File([image], `${timestampName()}.${image.type.split('/')[1] ?? 'png'}`, { type: image.type })
    if (image.type === 'image/gif' || image.type === 'image/webp') return original

    try {
        const bitmap = await createImageBitmap(image)
        const canvas = document.createElement('canvas')
        canvas.width = bitmap.width
        canvas.height = bitmap.height
        const ctx = canvas.getContext('2d')
        if (ctx === null) return original
        ctx.drawImage(bitmap, 0, 0)
        bitmap.close()

        const webp = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/webp', WEBP_QUALITY))
        if (webp === null || webp.type !== 'image/webp') return original

        return new File([webp], `${timestampName()}.webp`, { type: 'image/webp' })
    } catch {
        return original
    }
}

export function isImageMime(mimeType: string): boolean {
    return IMAGE_MIME_TYPES.has(mimeType)
}

export function isTextMime(mimeType: string): boolean {
    return TEXT_MIME_TYPES.has(mimeType)
}

export function canPreviewText(mimeType: string, sizeBytes: number): boolean {
    return isTextMime(mimeType) && sizeBytes <= TEXT_PREVIEW_BYTES
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`
    }

    const units = ['KB', 'MB', 'GB']
    let value = bytes / 1024
    let unit = 0
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024
        unit += 1
    }

    return `${value < 10 ? value.toFixed(1) : Math.round(value)} ${units[unit]}`
}
