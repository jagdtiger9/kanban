export const CARD_TABS = ['content', 'comments', 'log'] as const
export type CardTab = (typeof CARD_TABS)[number]

export function isCardTab(value: unknown): value is CardTab {
    return typeof value === 'string' && (CARD_TABS as readonly string[]).includes(value)
}
