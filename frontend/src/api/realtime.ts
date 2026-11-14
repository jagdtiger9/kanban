import { api } from './client'

export interface RealtimeTokenPayload {
    url: string
    channel: string
    token: string
    subscriptionToken: string
    expiresAt: number
}

export function getRealtimeToken(projectId: string): Promise<RealtimeTokenPayload> {
    return api<RealtimeTokenPayload>(`/realtime/token?projectId=${encodeURIComponent(projectId)}`)
}
