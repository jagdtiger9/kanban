export const TEXT_PREVIEW_BYTES = 256 * 1024
export const MAX_AVATAR_BYTES = 5 * 1024 * 1024
export const MAX_COMMENT_FILES = 5
export const MAX_COMMENT_BYTES = 50 * 1024 * 1024

export const MEDIA_ACCEPT =
    'image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain,text/csv,text/markdown,application/json'
export const AVATAR_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif'

const IMAGE_MIME_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
const TEXT_MIME_TYPES = new Set(['text/plain', 'text/csv', 'text/markdown', 'application/json'])

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
