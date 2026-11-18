import { onBeforeUnmount, watch, type Ref } from 'vue'
import { Centrifuge, type PublicationContext } from 'centrifuge'
import { getCallToken, type CallTokenPayload } from './api'
import { useCallStore } from './store'
import { parseCallMessage } from './types'

/**
 * App-wide connection to the user's personal call channel (call:<userId>),
 * separate from the per-project board connection: invitations must reach
 * the user on any page.
 */
export function useCallRealtime(userId: Ref<string | null>): void {
    const store = useCallStore()
    let client: Centrifuge | null = null
    let generation = 0

    function teardown(): void {
        generation++
        client?.disconnect()
        client = null
    }

    async function setup(id: string): Promise<void> {
        const current = ++generation
        let initial: CallTokenPayload
        try {
            initial = await getCallToken()
        } catch {
            return
        }
        if (current !== generation) return

        const instance = new Centrifuge(initial.url, {
            getToken: async () => (await getCallToken()).token,
        })
        const subscription = instance.newSubscription(initial.channel, {
            token: initial.subscriptionToken,
            getToken: async () => (await getCallToken()).subscriptionToken,
        })
        subscription.on('publication', (ctx: PublicationContext) => {
            const message = parseCallMessage(ctx.data)
            if (message !== null) store.handleMessage(message)
        })
        // Badges loaded after subscribing, so no room change falls in between.
        subscription.on('subscribed', () => void store.init(id))
        client = instance
        subscription.subscribe()
        instance.connect()
    }

    watch(
        userId,
        (id) => {
            teardown()
            store.reset()
            if (id !== null) void setup(id)
        },
        { immediate: true },
    )

    onBeforeUnmount(() => {
        teardown()
        store.reset()
    })
}
