import { onBeforeUnmount, ref, watch, type Ref } from 'vue'
import {
    Centrifuge,
    type ClientInfo,
    type JoinContext,
    type LeaveContext,
    type PublicationContext,
    type Subscription,
} from 'centrifuge'
import { getRealtimeToken, type RealtimeTokenPayload } from '../api/realtime'

export interface RealtimeChange {
    event: string
    actorId: string | null
    cardId: string | null
}

export interface BoardRealtime {
    /** User IDs with at least one live connection on this project's channel. */
    onlineUserIds: Ref<ReadonlySet<string>>
    /** null while the first presence lookup runs, false when the server refuses presence. */
    presenceAvailable: Ref<boolean | null>
    refreshPresence: () => Promise<void>
}

const REFRESH_DELAY_MS = 300

function parseChange(data: unknown): RealtimeChange | null {
    if (typeof data !== 'object' || data === null) return null
    const record = data as Record<string, unknown>
    if (record.type !== 'board.changed' || typeof record.event !== 'string') return null

    return {
        event: record.event,
        actorId: typeof record.actorId === 'string' ? record.actorId : null,
        cardId: typeof record.cardId === 'string' ? record.cardId : null,
    }
}

export function useBoardRealtime(
    projectId: Ref<string>,
    onChange: (change: RealtimeChange) => void,
    canRefresh: () => boolean = () => true,
): BoardRealtime {
    const onlineUserIds = ref<ReadonlySet<string>>(new Set())
    const presenceAvailable = ref<boolean | null>(null)

    let client: Centrifuge | null = null
    let subscription: Subscription | null = null
    let refreshTimer: ReturnType<typeof setTimeout> | null = null
    let pendingChange: RealtimeChange | null = null
    let generation = 0

    // One user can hold several connections (tabs). Presence is per connection,
    // so online users are the distinct users across the present client IDs.
    let presentClients = new Map<string, string>()

    function publishPresence(): void {
        onlineUserIds.value = new Set([...presentClients.values()].filter((userId) => userId !== ''))
    }

    function applyPresence(clients: Record<string, ClientInfo>): void {
        presentClients = new Map(Object.values(clients).map((info) => [info.client, info.user]))
        publishPresence()
    }

    async function refreshPresence(): Promise<void> {
        const active = subscription
        const current = generation
        if (active === null) {
            presenceAvailable.value = false
            return
        }
        try {
            const result = await active.presence()
            if (current !== generation) return
            applyPresence(result.clients)
            presenceAvailable.value = true
        } catch {
            if (current !== generation) return
            presenceAvailable.value = false
        }
    }

    function schedule(change: RealtimeChange): void {
        pendingChange = change
        if (refreshTimer !== null) clearTimeout(refreshTimer)
        refreshTimer = setTimeout(flush, REFRESH_DELAY_MS)
    }

    function flush(): void {
        refreshTimer = null
        const change = pendingChange
        if (change === null) return
        if (!canRefresh()) {
            refreshTimer = setTimeout(flush, REFRESH_DELAY_MS)
            return
        }
        pendingChange = null
        onChange(change)
    }

    function teardown(): void {
        generation++
        if (refreshTimer !== null) {
            clearTimeout(refreshTimer)
            refreshTimer = null
        }
        pendingChange = null
        presentClients = new Map()
        onlineUserIds.value = new Set()
        presenceAvailable.value = null
        client?.disconnect()
        client = null
        subscription = null
    }

    function fetchToken(id: string): Promise<RealtimeTokenPayload> {
        return getRealtimeToken(id)
    }

    async function setup(id: string): Promise<void> {
        const current = ++generation
        let initial: RealtimeTokenPayload
        try {
            initial = await fetchToken(id)
        } catch {
            return
        }
        if (current !== generation) return

        const instance = new Centrifuge(initial.url, {
            getToken: async () => (await fetchToken(id)).token,
        })
        const active = instance.newSubscription(initial.channel, {
            token: initial.subscriptionToken,
            getToken: async () => (await fetchToken(id)).subscriptionToken,
            recoverable: true,
            joinLeave: true,
        })
        let subscribedOnce = false
        active.on('subscribed', () => {
            void refreshPresence()
            if (subscribedOnce) {
                schedule({ event: 'reconnect', actorId: null, cardId: null })
                return
            }
            subscribedOnce = true
        })
        active.on('join', (ctx: JoinContext) => {
            presentClients.set(ctx.info.client, ctx.info.user)
            publishPresence()
        })
        active.on('leave', (ctx: LeaveContext) => {
            presentClients.delete(ctx.info.client)
            publishPresence()
        })
        active.on('publication', (ctx: PublicationContext) => {
            const change = parseChange(ctx.data)
            if (change !== null) schedule(change)
        })
        client = instance
        subscription = active
        active.subscribe()
        instance.connect()
    }

    watch(
        projectId,
        (id) => {
            teardown()
            void setup(id)
        },
        { immediate: true },
    )

    onBeforeUnmount(teardown)

    return { onlineUserIds, presenceAvailable, refreshPresence }
}
