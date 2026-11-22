export type CardRole = 'creator' | 'assignee' | 'executor'

export interface CardPerson {
    id: string
    name: string | null
    roles: CardRole[]
}

const ROLE_LABELS: Record<CardRole, string> = {
    creator: 'Creator',
    assignee: 'Assignee',
    executor: 'Executor',
}

const AVATAR_COLORS = [
    '#be123c',
    '#c2410c',
    '#b45309',
    '#047857',
    '#0f766e',
    '#0369a1',
    '#4338ca',
    '#6d28d9',
    '#a21caf',
    '#334155',
] as const

export function roleLabel(role: CardRole): string {
    return ROLE_LABELS[role]
}

export function initials(name: string | null | undefined): string {
    const parts = (name ?? '').trim().split(/\s+/).filter(Boolean)
    if (parts.length === 0) {
        return '?'
    }

    const first = parts[0]
    const last = parts[parts.length - 1]
    const letters = first === last ? first.slice(0, 1) : `${first[0]}${last[0]}`

    return letters.toUpperCase()
}

export function avatarColor(userId: string): string {
    let hash = 0x811c9dc5
    for (let i = 0; i < userId.length; i += 1) {
        hash ^= userId.charCodeAt(i)
        hash = Math.imul(hash, 0x01000193)
    }

    return AVATAR_COLORS[(hash >>> 0) % AVATAR_COLORS.length]
}

export function buildCardPeople(
    creatorId: string,
    assigneeIds: string[],
    memberNames: Map<string, string>,
): CardPerson[] {
    const people = new Map<string, CardPerson>()

    people.set(creatorId, {
        id: creatorId,
        name: memberNames.get(creatorId) ?? null,
        roles: ['creator'],
    })

    for (const userId of assigneeIds) {
        const existing = people.get(userId)
        if (existing !== undefined) {
            existing.roles.push('assignee')
            continue
        }

        people.set(userId, {
            id: userId,
            name: memberNames.get(userId) ?? null,
            roles: ['assignee'],
        })
    }

    return [...people.values()]
}
