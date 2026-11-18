export type SignalKind = 'offer' | 'answer' | 'ice' | 'state'

export interface RoomParticipant {
    userId: string
    sessionId: string
    displayName: string
}

export interface MediaState {
    mic: boolean
    cam: boolean
    screen: boolean
}

export interface RoomChangedMessage {
    type: 'room.changed'
    projectId: string
    participants: RoomParticipant[]
}

export interface InviteMessage {
    type: 'invite'
    projectId: string
    projectName: string
    fromUserId: string
    fromName: string
}

export interface InviteDeclinedMessage {
    type: 'invite.declined'
    projectId: string
    userId: string
    displayName: string
}

export interface SignalMessage {
    type: 'signal'
    projectId: string
    fromUserId: string
    fromSessionId: string
    toSessionId: string
    kind: SignalKind
    data: Record<string, unknown>
}

export type CallMessage = RoomChangedMessage | InviteMessage | InviteDeclinedMessage | SignalMessage

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null
}

/** Accepts only the message shapes the backend publishes on call:<userId>. */
export function parseCallMessage(data: unknown): CallMessage | null {
    if (!isRecord(data) || typeof data.type !== 'string' || typeof data.projectId !== 'string') return null
    switch (data.type) {
        case 'room.changed':
            return Array.isArray(data.participants) ? (data as unknown as RoomChangedMessage) : null
        case 'invite':
            return typeof data.fromUserId === 'string' ? (data as unknown as InviteMessage) : null
        case 'invite.declined':
            return typeof data.userId === 'string' ? (data as unknown as InviteDeclinedMessage) : null
        case 'signal':
            return typeof data.fromUserId === 'string' && typeof data.kind === 'string' && isRecord(data.data)
                ? (data as unknown as SignalMessage)
                : null
        default:
            return null
    }
}
