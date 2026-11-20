import { onBeforeUnmount, watch, type Ref } from 'vue'
import { Centrifuge, type PublicationContext } from 'centrifuge'
import { getUserRealtimeToken, type UserEventItem } from '../api/userEvents'
import type { RealtimeTokenPayload } from '../api/realtime'
import { useUserEventsStore } from '../stores/userEvents'

const SEEN_DELAY_MS = 700

function parseItem(data: unknown): UserEventItem | null {
    if (typeof data !== 'object' || data === null) return null
    const record = data as Record<string, unknown>
    if (record.type !== 'user.event' || typeof record.data !== 'object' || record.data === null) return null
    const item = record.data as Record<string, unknown>
    if (typeof item.id !== 'string' || typeof item.type !== 'string' || typeof item.occurredOn !== 'string') {
        return null
    }

    return {
        id: item.id,
        type: item.type,
        actorId: typeof item.actorId === 'string' ? item.actorId : null,
        actorName: typeof item.actorName === 'string' ? item.actorName : null,
        projectId: typeof item.projectId === 'string' ? item.projectId : null,
        projectName: typeof item.projectName === 'string' ? item.projectName : null,
        cardId: typeof item.cardId === 'string' ? item.cardId : null,
        cardTitle: typeof item.cardTitle === 'string' ? item.cardTitle : null,
        role: typeof item.role === 'string' ? item.role : null,
        occurredOn: item.occurredOn,
        seenAt: typeof item.seenAt === 'string' ? item.seenAt : null,
    }
}

export function useUserRealtime(
    userId: Ref<string | null>,
    canMarkSeen: () => boolean,
): void {
    const store = useUserEventsStore()
    let client: Centrifuge | null = null
    let seenTimer: ReturnType<typeof setTimeout> | null = null
    let generation = 0

    function scheduleSeen(): void {
        if (seenTimer !== null) clearTimeout(seenTimer)
        seenTimer = setTimeout(() => {
            seenTimer = null
            void store.markSeen()
        }, SEEN_DELAY_MS)
    }

    function teardown(): void {
        generation++
        if (seenTimer !== null) {
            clearTimeout(seenTimer)
            seenTimer = null
        }
        client?.disconnect()
        client = null
    }

    function fetchToken(): Promise<RealtimeTokenPayload> {
        return getUserRealtimeToken()
    }

    async function setup(): Promise<void> {
        const current = ++generation
        let initial: RealtimeTokenPayload
        try {
            initial = await fetchToken()
        } catch {
            return
        }
        if (current !== generation) return

        const instance = new Centrifuge(initial.url, {
            getToken: async () => (await fetchToken()).token,
        })
        const subscription = instance.newSubscription(initial.channel, {
            token: initial.subscriptionToken,
            getToken: async () => (await fetchToken()).subscriptionToken,
        })
        let subscribedOnce = false
        subscription.on('subscribed', () => {
            if (subscribedOnce) {
                // Events published while disconnected are only in the database.
                void store.load()
                return
            }
            subscribedOnce = true
        })
        subscription.on('publication', (ctx: PublicationContext) => {
            const item = parseItem(ctx.data)
            if (item === null) return
            store.prepend(item)
            if (canMarkSeen()) scheduleSeen()
        })
        client = instance
        subscription.subscribe()
        instance.connect()
    }

    watch(
        userId,
        (id) => {
            teardown()
            if (id !== null && id !== '') void setup()
        },
        { immediate: true },
    )

    onBeforeUnmount(teardown)
}
