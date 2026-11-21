import { onBeforeUnmount, watch, type Ref } from 'vue'
import { Centrifuge, type PublicationContext } from 'centrifuge'
import { getCallToken, type CallTokenPayload } from './api'
import { useCallStore } from './store'
import { parseCallMessage } from './types'

// Centrifugo server error codes, see https://centrifugal.dev/docs/server/codes
const UNKNOWN_CHANNEL = 102
const PERMISSION_DENIED = 103
const INVALID_TOKEN = 3500

// Each setup problem is reported once per page load, not on every reconnect attempt.
const reported = new Set<string>()

function reportOnce(key: string, message: string, detail?: unknown): void {
    if (reported.has(key)) return
    reported.add(key)
    const text = `[call] Calls disabled: ${message}. See docs/calls-server.md`
    if (detail === undefined) console.error(text)
    else console.error(text, detail)
}

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
        } catch (e) {
            reportOnce('token', 'the call token request (/api/call/token) failed', e)
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
        subscription.on('subscribed', () => {
            store.setRealtimeReady(true)
            void store.init(id)
        })
        subscription.on('subscribing', () => store.setRealtimeReady(false))
        subscription.on('unsubscribed', (ctx) => {
            store.setRealtimeReady(false)
            const detail = `${ctx.channel}: ${ctx.code} ${ctx.reason}`
            if (ctx.code === UNKNOWN_CHANNEL) {
                reportOnce('namespace', `Centrifugo has no "call" namespace (${detail})`)
            } else if (ctx.code === PERMISSION_DENIED) {
                reportOnce('permission', `Centrifugo denied the call channel subscription (${detail})`)
            } else if (ctx.code !== 0) {
                reportOnce(`unsubscribed-${ctx.code}`, `unsubscribed from the call channel (${detail})`)
            }
        })
        subscription.on('error', (ctx) => {
            console.warn(`[call] subscription error on ${ctx.channel} (${ctx.type})`, ctx.error)
        })
        instance.on('error', (ctx) => {
            if (ctx.type === 'transport') {
                reportOnce('transport', `cannot connect to Centrifugo at ${initial.url}`, ctx.error)
            } else {
                console.warn('[call] realtime connection error', ctx.error)
            }
        })
        instance.on('disconnected', (ctx) => {
            if (ctx.code === INVALID_TOKEN) {
                reportOnce('connect-token', `Centrifugo rejected the connection token (${ctx.code} ${ctx.reason})`)
            }
        })
        client = instance
        subscription.subscribe()
        instance.connect()
    }

    watch(
        userId,
        (id) => {
            teardown()
            store.reset()
            if (id !== null) {
                store.setSelf(id)
                void setup(id)
            }
        },
        { immediate: true },
    )

    onBeforeUnmount(() => {
        teardown()
        store.reset()
    })
}
