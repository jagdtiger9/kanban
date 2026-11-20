export function toYmd(date: Date): string {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

export function fromYmd(ymd: string | null): Date | null {
    if (ymd === null) return null
    const [year, month, day] = ymd.split('-').map(Number)
    return new Date(year, month - 1, day)
}

export function relativeTime(iso: string): string {
    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return ''
    const seconds = Math.round((Date.now() - date.getTime()) / 1000)
    if (seconds < 45) return 'just now'
    const minutes = Math.round(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.round(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.round(hours / 24)
    if (days < 7) return `${days}d ago`
    return date.toLocaleDateString()
}
