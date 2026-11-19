import { onBeforeUnmount, watch, type Ref } from 'vue'
import { Centrifuge, type PublicationContext } from 'centrifuge'
import { getRealtimeToken, type RealtimeTokenPayload } from '../api/realtime'

export interface RealtimeChange {
    event: string
    actorId: string | null
    cardId: string | null
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
): void {
    let client: Centrifuge | null = null
    let refreshTimer: ReturnType<typeof setTimeout> | null = null
    let pendingChange: RealtimeChange | null = null
    let generation = 0

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
        client?.disconnect()
        client = null
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
        const subscription = instance.newSubscription(initial.channel, {
            token: initial.subscriptionToken,
            getToken: async () => (await fetchToken(id)).subscriptionToken,
            recoverable: true,
        })
        let subscribedOnce = false
        subscription.on('subscribed', () => {
            if (subscribedOnce) {
                schedule({ event: 'reconnect', actorId: null, cardId: null })
                return
            }
            subscribedOnce = true
        })
        subscription.on('publication', (ctx: PublicationContext) => {
            const change = parseChange(ctx.data)
            if (change !== null) schedule(change)
        })
        client = instance
        subscription.subscribe()
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
}
