import { api } from './client'
import type { RealtimeTokenPayload } from './realtime'

export interface UserEventItem {
    id: string
    type: string
    actorId: string | null
    actorName: string | null
    projectId: string | null
    projectName: string | null
    cardId: string | null
    cardTitle: string | null
    role: string | null
    occurredOn: string
    seenAt: string | null
}

export interface UserEventPage {
    items: UserEventItem[]
    nextCursor: string | null
}

export interface UnreadCountPayload {
    count: number
}

export function listUserEvents(cursor: string | null = null, limit = 20): Promise<UserEventPage> {
    const params = new URLSearchParams({ limit: String(limit) })
    if (cursor !== null) {
        params.set('cursor', cursor)
    }
    return api<UserEventPage>(`/user-events?${params.toString()}`)
}

export function getUnreadUserEventCount(): Promise<UnreadCountPayload> {
    return api<UnreadCountPayload>('/user-events/unread-count')
}

export function markUserEventsSeen(): Promise<void> {
    return api<void>('/user-events/seen', { method: 'POST' })
}

export function getUserRealtimeToken(): Promise<RealtimeTokenPayload> {
    return api<RealtimeTokenPayload>('/realtime/user-token')
}
