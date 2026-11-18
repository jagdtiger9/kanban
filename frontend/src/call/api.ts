import { api } from '../api/client'
import type { RoomParticipant, SignalKind } from './types'

export interface CallTokenPayload {
    url: string
    channel: string
    token: string
    subscriptionToken: string
    expiresAt: number
}

export interface ActiveCall {
    projectId: string
    participants: RoomParticipant[]
}

export interface JoinPayload {
    participants: RoomParticipant[]
    iceServers: RTCIceServer[]
}

function post<T>(projectId: string, action: string, body: object): Promise<T> {
    return api<T>(`/projects/${encodeURIComponent(projectId)}/call/${action}`, {
        method: 'POST',
        body: JSON.stringify(body),
    })
}

export function getCallToken(): Promise<CallTokenPayload> {
    return api<CallTokenPayload>('/call/token')
}

export async function listActiveCalls(): Promise<ActiveCall[]> {
    return (await api<{ calls: ActiveCall[] }>('/call/rooms')).calls
}

export function joinCall(projectId: string, sessionId: string): Promise<JoinPayload> {
    return post<JoinPayload>(projectId, 'join', { sessionId })
}

export function leaveCall(projectId: string, sessionId: string): Promise<void> {
    return post<void>(projectId, 'leave', { sessionId })
}

/** Leave while the page is closing: fetch may be cancelled, a beacon is not. */
export function leaveCallOnUnload(projectId: string, sessionId: string): void {
    const blob = new Blob([JSON.stringify({ sessionId })], { type: 'application/json' })
    navigator.sendBeacon(`/api/projects/${encodeURIComponent(projectId)}/call/leave`, blob)
}

export async function heartbeat(projectId: string, sessionId: string): Promise<boolean> {
    return (await post<{ inRoom: boolean }>(projectId, 'heartbeat', { sessionId })).inRoom
}

export function sendSignal(
    projectId: string,
    sessionId: string,
    toUserId: string,
    toSessionId: string,
    kind: SignalKind,
    data: Record<string, unknown>,
): Promise<void> {
    return post<void>(projectId, 'signal', { sessionId, toUserId, toSessionId, kind, data })
}

export function inviteToCall(projectId: string, userId: string): Promise<void> {
    return post<void>(projectId, 'invite', { userId })
}

export function declineCall(projectId: string, fromUserId: string): Promise<void> {
    return post<void>(projectId, 'decline', { fromUserId })
}
