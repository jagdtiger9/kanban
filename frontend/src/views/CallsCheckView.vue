<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import { Centrifuge } from 'centrifuge'
import { ApiError } from '../api/client'
import { checkCalls, getCallToken, type CallCheckPayload, type CallCheckStatus } from '../call/api'

/**
 * Calls diagnostics (docs/calls-server.md): what the server sees (Centrifugo, the
 * "call" namespace, STUN/TURN settings and ports) and what this browser sees
 * (WebSocket, its own call channel, a STUN/TURN gathering test). Nothing secret
 * is shown, so it is open to every signed-in user.
 */

const TIMEOUT_MS = 8000

const running = ref(false)
const serverChecks = ref<CallCheckPayload[]>([])
const browserChecks = ref<CallCheckPayload[]>([])
const checkedAt = ref<string | null>(null)
// Everything as it came, for copying to the developers (TURN password masked).
const raw = ref<unknown>(null)

function result(key: string, label: string, status: CallCheckStatus, detail: string, hint: string | null = null): CallCheckPayload {
    return { key, label, status, detail, hint }
}

function withTimeout<T>(promise: Promise<T>, what: string): Promise<T> {
    return Promise.race([
        promise,
        new Promise<T>((_, reject) => setTimeout(() => reject(new Error(`${what}: no answer in ${TIMEOUT_MS / 1000} s`)), TIMEOUT_MS)),
    ])
}

// ---- browser: WebSocket and the own call channel ----------------------------

async function realtimeChecks(): Promise<CallCheckPayload[]> {
    let token
    try {
        token = await getCallToken()
    } catch (e) {
        return [result('ws', 'WebSocket from this browser', 'fail', `Could not get call tokens: ${e instanceof Error ? e.message : e}`, 'See "Call tokens" above.')]
    }

    const client = new Centrifuge(token.url, { token: token.token })
    const checks: CallCheckPayload[] = []
    // The setting in use and where this page runs: Centrifugo checks the page origin
    // against allowed_origins, and a WebSocket on another host is a typical mismatch.
    let wsHost = '?'
    try {
        wsHost = new URL(token.url).host
    } catch {
        // an unparsable CENTRIFUGO_WS_URL shows up as is below
    }
    const setting = `CENTRIFUGO_WS_URL = ${token.url}; page origin = ${location.origin}`
        + (wsHost === location.host ? '' : ` (different hosts: ${wsHost} vs ${location.host})`)
    try {
        const started = performance.now()
        await withTimeout(
            new Promise<void>((resolve, reject) => {
                client.on('connected', () => resolve())
                client.on('disconnected', (ctx) => reject(new Error(`disconnected: ${ctx.code} ${ctx.reason}`)))
                client.on('error', (ctx) => {
                    if (ctx.type === 'transport') reject(new Error(`cannot connect (${ctx.error.message})`))
                })
                client.connect()
            }),
            'WebSocket',
        )
        checks.push(result('ws', 'WebSocket from this browser', 'ok', `Connected in ${Math.round(performance.now() - started)} ms; ${setting}`))
    } catch (e) {
        client.disconnect()
        const differentHost = wsHost !== location.host
        return [result('ws', 'WebSocket from this browser', 'fail', `${e instanceof Error ? e.message : String(e)}; ${setting}`,
            differentHost
                ? `The WebSocket goes to another host than this page: open the site at https://${wsHost}, or set CENTRIFUGO_WS_URL to wss://${location.host}/_ws_connection/websocket.`
                : `Is the /_ws_connection/ proxy in place on ${location.host}? Does Centrifugo allowed_origins contain ${location.origin}?`)]
    }

    try {
        const subscription = client.newSubscription(token.channel, { token: token.subscriptionToken })
        await withTimeout(
            new Promise<void>((resolve, reject) => {
                subscription.on('subscribed', () => resolve())
                subscription.on('unsubscribed', (ctx) => reject(new Error(`${ctx.code} ${ctx.reason}`)))
                subscription.subscribe()
            }),
            'Subscription',
        )
        checks.push(result('subscription', 'Own call channel', 'ok', `Subscribed to ${token.channel}`))
    } catch (e) {
        const detail = e instanceof Error ? e.message : String(e)
        const hint = detail.startsWith('102')
            ? 'The running Centrifugo has no "call" namespace: add it to the config and restart Centrifugo.'
            : 'See docs/calls-server.md, "Console errors".'
        checks.push(result('subscription', 'Own call channel', 'fail', detail, hint))
    } finally {
        client.disconnect()
    }
    return checks
}

// ---- browser: STUN / TURN -------------------------------------------------------

/** Gathers ICE candidates with the call's own servers and reports what came back. */
async function iceChecks(iceServers: RTCIceServer[]): Promise<CallCheckPayload[]> {
    const hasTurn = iceServers.some((server) => [server.urls].flat().some((url) => url.startsWith('turn')))
    if (iceServers.length === 0) {
        return [result('ice', 'STUN/TURN from this browser', 'warn', 'No STUN/TURN servers configured', 'Only direct connections (same network) will work.')]
    }

    const pc = new RTCPeerConnection({ iceServers })
    const types = new Set<string>()
    const relays: string[] = []
    const publicAddresses: string[] = []
    const candidates: string[] = []
    const errors: string[] = []
    pc.onicecandidate = (event) => {
        const candidate = event.candidate
        if (candidate === null) return
        if (candidate.type !== null) types.add(candidate.type)
        if (candidate.type === 'relay' && candidate.address) relays.push(candidate.address)
        if (candidate.type === 'srflx' && candidate.address) publicAddresses.push(candidate.address)
        candidates.push(`${candidate.type ?? '?'} ${candidate.protocol ?? ''} ${candidate.address ?? '(hidden)'}:${candidate.port ?? ''}`)
    }
    pc.onicecandidateerror = (event) => {
        const e = event as RTCPeerConnectionIceErrorEvent
        errors.push(`${e.url}: ${e.errorCode} ${e.errorText}`)
    }
    try {
        pc.createDataChannel('check')
        await pc.setLocalDescription(await pc.createOffer())
        await withTimeout(
            new Promise<void>((resolve) => {
                pc.onicegatheringstatechange = () => {
                    if (pc.iceGatheringState === 'complete') resolve()
                }
            }),
            'ICE gathering',
        ).catch(() => undefined)
    } finally {
        pc.close()
    }

    const checks: CallCheckPayload[] = []
    checks.push(types.has('srflx')
        ? result('stun', 'STUN from this browser', 'ok', `Public address of this browser: ${[...new Set(publicAddresses)].join(', ')}`)
        : result('stun', 'STUN from this browser', 'warn', 'No public address (srflx) received',
            'STUN is not reachable from this network (UDP blocked?) or not configured.'))

    checks.push(result('candidates', 'ICE candidates gathered', candidates.length > 0 ? 'ok' : 'warn',
        candidates.length > 0 ? candidates.join('; ') : 'None',
        'host = local network, srflx = public address via STUN, relay = via TURN. Calls between different networks need srflx or relay.'))

    if (hasTurn) {
        const unauthorized = errors.some((line) => / 401 /.test(line))
        checks.push(relays.length > 0
            ? result('turn', 'TURN relay from this browser', 'ok', `Relay candidate via ${[...new Set(relays)].join(', ')}`)
            : result('turn', 'TURN relay from this browser', 'fail',
                errors.length > 0 ? errors.join('; ') : 'No relay candidate received',
                unauthorized
                    ? 'TURN rejected the credentials (401): CALL_TURN_SECRET must equal coturn static-auth-secret.'
                    : 'coturn is not reachable from this network: check it runs and the ports (3478 udp/tcp, 5349, 49160-49999 udp) are open.'))
    }
    return checks
}

// ---- run --------------------------------------------------------------------------

/** Runs a browser check; a crash becomes a FAIL line instead of breaking the page. */
async function guarded(key: string, label: string, check: () => Promise<CallCheckPayload[]>): Promise<CallCheckPayload[]> {
    try {
        return await check()
    } catch (e) {
        return [result(key, label, 'fail', `The check itself crashed: ${e instanceof Error ? e.message : String(e)}`,
            'Send this line to the developers.')]
    }
}

/** Always shows something: a failed server request still lets the browser checks run. */
async function run(): Promise<void> {
    running.value = true
    serverChecks.value = []
    browserChecks.value = []
    let iceServers: RTCIceServer[] = []
    let serverAnswer: unknown = null
    try {
        const report = await checkCalls()
        serverChecks.value = report.checks
        iceServers = report.iceServers
        serverAnswer = {
            ...report,
            iceServers: report.iceServers.map((server) => (server.credential ? { ...server, credential: '********' } : server)),
        }
    } catch (e) {
        serverAnswer = { error: e instanceof Error ? e.message : String(e) }
        const message = e instanceof ApiError ? `${e.status} ${e.message}` : e instanceof Error ? e.message : String(e)
        serverChecks.value = [result('server', 'Server checks (/api/call/check)', 'fail', `The request failed: ${message}`,
            'The app backend did not answer; ask the admin to run: php bin/console app:calls:check')]
    }
    browserChecks.value = [
        ...await guarded('ws', 'WebSocket from this browser', realtimeChecks),
        ...await guarded('ice', 'STUN/TURN from this browser', () => iceChecks(iceServers)),
    ]
    checkedAt.value = new Date().toLocaleString()
    raw.value = {
        checkedAt: new Date().toISOString(),
        page: location.href,
        userAgent: navigator.userAgent,
        server: serverAnswer,
        browser: browserChecks.value,
    }
    running.value = false
}

onMounted(run)

const STATUS_CLASS: Record<CallCheckStatus, string> = {
    ok: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    warn: 'bg-warn/15 text-warn-text',
    fail: 'bg-danger/15 text-danger-text',
}
</script>

<template>
    <main class="flex flex-1 flex-col items-center overflow-y-auto p-6">
        <div class="w-full max-w-3xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Calls check</h1>
                    <p class="mt-1 text-sm text-muted">
                        What calls depend on, seen from the server and from this browser.
                        Setup and fixes: <code>docs/calls-server.md</code>.
                    </p>
                </div>
                <Button :label="running ? 'Checking…' : 'Check again'" :disabled="running" @click="run"/>
            </div>

            <section v-for="group in [{ title: 'Server', checks: serverChecks }, { title: 'This browser', checks: browserChecks }]"
                     :key="group.title" class="space-y-2">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-faint">{{ group.title }}</h2>
                <p v-if="running && group.checks.length === 0" class="text-sm text-faint">Checking…</p>
                <div v-for="check in group.checks" :key="check.key"
                     class="flex items-start gap-3 rounded-lg border border-line bg-panel/60 px-3 py-2">
                    <span :class="['mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-xs font-semibold uppercase', STATUS_CLASS[check.status]]">
                        {{ check.status }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-strong">{{ check.label }}</p>
                        <p class="whitespace-pre-line break-words text-sm text-body">{{ check.detail }}</p>
                        <p v-if="check.hint" class="mt-0.5 text-xs text-muted">{{ check.hint }}</p>
                    </div>
                </div>
            </section>

            <p v-if="checkedAt" class="text-xs text-faint">Checked {{ checkedAt }}</p>
            <details v-if="raw !== null" class="rounded-lg border border-line bg-panel/60 px-3 py-2">
                <summary class="cursor-pointer text-sm font-medium text-strong">Raw data (copy this to the developers)</summary>
                <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-all text-xs text-body">{{ JSON.stringify(raw, null, 2) }}</pre>
            </details>
            <p class="text-xs text-faint">
                The same server checks from the server console (for the admin, or a deploy script —
                exits with an error when a check fails):
                <code class="rounded bg-raised px-1.5 py-0.5 text-body">php bin/console app:calls:check</code>
            </p>
        </div>
    </main>
</template>
