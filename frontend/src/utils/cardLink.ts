const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

/**
 * Accepts a bare card UUID or a copied card URL (…?card=<uuid>) and returns
 * the card id, or null when the input holds no recognisable card reference.
 */
export function parseCardRef(raw: string): string | null {
    const value = raw.trim()
    if (value === '') return null
    if (UUID_PATTERN.test(value)) return value.toLowerCase()

    try {
        const card = new URL(value, window.location.origin).searchParams.get('card')
        return card !== null && UUID_PATTERN.test(card) ? card.toLowerCase() : null
    } catch {
        return null
    }
}
