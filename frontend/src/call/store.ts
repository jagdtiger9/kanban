import { computed, markRaw, ref } from 'vue'
import { defineStore } from 'pinia'
import { ApiError } from '../api/client'
import { getProject, type MemberPayload } from '../api/projects'
import * as callApi from './api'
import { PeerMesh, type PeerStatus } from './peers'
import { startRing } from './ring'
import type {
    CallMessage,
    InviteMessage,
    MediaState,
    RoomParticipant,
    SignalKind,
    SignalMessage,
} from './types'

export interface CallTile {
    userId: string
    displayName: string
    stream: MediaStream | null
    status: PeerStatus
    media: MediaState
}

// picking: the window is open on the member list, no microphone, not in the room yet
type CallStatus = 'idle' | 'picking' | 'joining' | 'active'

const HEARTBEAT_MS = 20000
const INVITE_TIMEOUT_MS = 30000
const NOTICE_MS = 5000

function newSessionId(): string {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID()
    return Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, '0')).join('')
}

function mediaError(error: unknown): string {
    if (!window.isSecureContext || navigator.mediaDevices === undefined) {
        return 'Calls need HTTPS: open the site over https://'
    }
    if (error instanceof DOMException) {
        if (error.name === 'NotAllowedError') return 'Allow microphone access in the browser'
        if (error.name === 'NotFoundError') return 'No microphone found'
    }
    return 'Could not start the microphone'
}

/**
 * Calls between project members (frontend part of src/Call). One call at a
 * time per tab; the WebRTC objects live outside reactive state (markRaw /
 * plain variables), the store only mirrors what the UI shows.
 */
export const useCallStore = defineStore('call', () => {
    const selfId = ref<string | null>(null)
    // true while the personal call channel (call:<userId>) is subscribed
    const realtimeReady = ref(false)
    const rooms = ref<Record<string, RoomParticipant[]>>({})
    const invite = ref<InviteMessage | null>(null)
    const notice = ref<string | null>(null)

    const status = ref<CallStatus>('idle')
    const projectId = ref<string | null>(null)
    const projectName = ref('')
    const members = ref<MemberPayload[]>([])
    const tiles = ref<CallTile[]>([])
    const local = ref<{ stream: MediaStream | null; media: MediaState }>({
        stream: null,
        media: { mic: true, cam: false, screen: false },
    })
    const error = ref<string | null>(null)
    const minimized = ref(false)
    const inviting = ref<Record<string, 'ringing' | 'declined'>>({})

    // Non-reactive call internals
    let sessionId = ''
    let mesh: PeerMesh | null = null
    let heartbeatTimer: number | null = null
    let inviteTimer: number | null = null
    let noticeTimer: number | null = null
    let stopRing: (() => void) | null = null
    let cameraTrack: MediaStreamTrack | null = null
    let screenTrack: MediaStreamTrack | null = null
    let sendQueue: Promise<void> = Promise.resolve()
    let receiveQueue: Promise<void> = Promise.resolve()
    // Offers can arrive while our own join request is still in flight.
    let earlySignals: SignalMessage[] = []

    const inCall = computed(() => status.value !== 'idle')

    function participantsOf(id: string): RoomParticipant[] {
        return rooms.value[id] ?? []
    }

    function isInRoom(id: string, userId: string): boolean {
        return participantsOf(id).some((p) => p.userId === userId)
    }

    // ---- bootstrapping -------------------------------------------------

    /** Known as soon as the user is authenticated, before the call channel connects. */
    function setSelf(userId: string): void {
        selfId.value = userId
    }

    function setRealtimeReady(ready: boolean): void {
        realtimeReady.value = ready
    }

    async function init(userId: string): Promise<void> {
        selfId.value = userId
        try {
            const calls = await callApi.listActiveCalls()
            const next: Record<string, RoomParticipant[]> = {}
            for (const call of calls) next[call.projectId] = call.participants
            rooms.value = next
        } catch {
            // badges are a convenience; realtime updates fill them in later
        }
    }

    function reset(): void {
        void leave()
        dismissInvite()
        rooms.value = {}
        selfId.value = null
        realtimeReady.value = false
    }

    function showNotice(text: string): void {
        notice.value = text
        if (noticeTimer !== null) window.clearTimeout(noticeTimer)
        noticeTimer = window.setTimeout(() => {
            notice.value = null
            noticeTimer = null
        }, NOTICE_MS)
    }

    // ---- realtime messages ---------------------------------------------

    function handleMessage(message: CallMessage): void {
        switch (message.type) {
            case 'room.changed':
                onRoomChanged(message.projectId, message.participants)
                break
            case 'invite':
                onInvite(message)
                break
            case 'invite.declined':
                if (message.projectId === projectId.value) {
                    inviting.value = { ...inviting.value, [message.userId]: 'declined' }
                    showNotice(`${message.displayName} declined`)
                }
                break
            case 'signal':
                receiveQueue = receiveQueue.then(() => onSignal(message)).catch(() => {})
                break
        }
    }

    function onRoomChanged(id: string, participants: RoomParticipant[]): void {
        const next = { ...rooms.value }
        if (participants.length === 0) delete next[id]
        else next[id] = participants
        rooms.value = next

        if (invite.value?.projectId === id && participants.length === 0) dismissInvite()

        if (id !== projectId.value || status.value !== 'active') return

        const mine = participants.find((p) => p.userId === selfId.value)
        if (mine === undefined) {
            void leave('You were disconnected from the call', false)
            return
        }
        if (mine.sessionId !== sessionId) {
            void leave('You joined from another tab', false)
            return
        }

        const others = participants.filter((p) => p.userId !== selfId.value)
        const ids = new Set(others.map((p) => p.userId))
        for (const tile of tiles.value) {
            if (!ids.has(tile.userId)) mesh?.remove(tile.userId)
        }
        tiles.value = tiles.value.filter((t) => ids.has(t.userId))
        for (const p of others) {
            if (!tiles.value.some((t) => t.userId === p.userId)) addTile(p)
            if (inviting.value[p.userId] !== undefined) {
                const rest = { ...inviting.value }
                delete rest[p.userId]
                inviting.value = rest
            }
        }
    }

    function onInvite(message: InviteMessage): void {
        if (inCall.value && projectId.value === message.projectId) return
        dismissInvite()
        invite.value = message
        stopRing = startRing()
        inviteTimer = window.setTimeout(dismissInvite, INVITE_TIMEOUT_MS)
    }

    async function onSignal(message: SignalMessage): Promise<void> {
        if (message.projectId !== projectId.value || message.toSessionId !== sessionId) return
        if (mesh === null) {
            if (status.value === 'joining') earlySignals.push(message)
            return
        }
        if (!tiles.value.some((t) => t.userId === message.fromUserId)) {
            const known = participantsOf(message.projectId).find((p) => p.userId === message.fromUserId)
            addTile({
                userId: message.fromUserId,
                sessionId: message.fromSessionId,
                displayName: known?.displayName ?? '…',
            })
        }
        await mesh.handleSignal(message.fromUserId, message.fromSessionId, message.kind, message.data)
    }

    // ---- invite popup --------------------------------------------------

    function dismissInvite(): void {
        stopRing?.()
        stopRing = null
        if (inviteTimer !== null) window.clearTimeout(inviteTimer)
        inviteTimer = null
        invite.value = null
    }

    async function acceptInvite(): Promise<void> {
        const current = invite.value
        dismissInvite()
        if (current === null) return
        await start(current.projectId)
    }

    async function declineInvite(): Promise<void> {
        const current = invite.value
        dismissInvite()
        if (current === null) return
        await callApi.declineCall(current.projectId, current.fromUserId).catch(() => {})
    }

    // ---- the call ------------------------------------------------------

    function addTile(p: RoomParticipant): void {
        tiles.value = [
            ...tiles.value,
            {
                userId: p.userId,
                displayName: p.displayName,
                stream: null,
                status: 'connecting',
                media: { mic: true, cam: false, screen: false },
            },
        ]
    }

    function updateTile(userId: string, patch: Partial<CallTile>): void {
        tiles.value = tiles.value.map((t) => (t.userId === userId ? { ...t, ...patch } : t))
    }

    function queueSignal(toUserId: string, toSessionId: string, kind: SignalKind, data: Record<string, unknown>): void {
        const id = projectId.value
        if (id === null) return
        const session = sessionId
        // Keep order: an ICE candidate must not overtake its offer.
        sendQueue = sendQueue
            .then(() => callApi.sendSignal(id, session, toUserId, toSessionId, kind, data))
            .catch(() => {})
    }

    /** "Call" button: open the window on the member list, nothing starts yet. */
    async function open(id: string): Promise<void> {
        if (inCall.value) {
            if (projectId.value === id) {
                minimized.value = false
                return
            }
            await leave()
        }

        // Without the call channel neither invites nor signals arrive.
        if (!realtimeReady.value || selfId.value === null) {
            error.value = 'Calls are unavailable: no connection to the realtime server'
            return
        }

        error.value = null
        status.value = 'picking'
        projectId.value = id
        projectName.value = ''
        members.value = []
        minimized.value = false
        try {
            const detail = await getProject(id)
            if (status.value !== 'picking' || projectId.value !== id) return
            projectName.value = detail.project.name
            members.value = detail.members
        } catch (e) {
            if (status.value !== 'picking' || projectId.value !== id) return
            status.value = 'idle'
            projectId.value = null
            error.value = e instanceof Error ? e.message : 'Could not load the project'
        }
    }

    /** Click on a member in the list: join the room, then ring them. */
    async function callMember(userId: string): Promise<void> {
        const id = projectId.value
        if (id === null) return
        if (status.value === 'picking') await start(id)
        if (status.value === 'active' && projectId.value === id) await inviteMember(userId)
    }

    async function start(id: string): Promise<void> {
        if (status.value === 'joining' || status.value === 'active') {
            if (projectId.value === id) {
                minimized.value = false
                return
            }
            await leave()
        }

        error.value = null
        status.value = 'joining'
        projectId.value = id
        minimized.value = false
        inviting.value = {}

        let stream: MediaStream
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true })
        } catch (e) {
            error.value = mediaError(e)
            status.value = 'idle'
            projectId.value = null
            return
        }

        sessionId = newSessionId()
        let joined: callApi.JoinPayload
        try {
            const [payload, detail] = await Promise.all([callApi.joinCall(id, sessionId), getProject(id)])
            joined = payload
            projectName.value = detail.project.name
            members.value = detail.members
        } catch (e) {
            stream.getTracks().forEach((t) => t.stop())
            error.value =
                e instanceof ApiError && e.status === 409 ? 'Room is full' : e instanceof Error ? e.message : 'Could not join the call'
            status.value = 'idle'
            projectId.value = null
            return
        }

        local.value = { stream: markRaw(stream), media: { mic: true, cam: false, screen: false } }
        mesh = markRaw(
            new PeerMesh(joined.iceServers, stream, {
                sendSignal: queueSignal,
                onStream: (userId, s) => updateTile(userId, { stream: markRaw(s) }),
                onStatus: (userId, s) => updateTile(userId, { status: s }),
                onMediaState: (userId, media) => updateTile(userId, { media }),
            }),
        )

        tiles.value = []
        rooms.value = { ...rooms.value, [id]: joined.participants }
        status.value = 'active'

        for (const p of joined.participants) {
            if (p.userId === selfId.value || p.sessionId === sessionId) continue
            addTile(p)
            void mesh.connect(p.userId, p.sessionId).catch(() => updateTile(p.userId, { status: 'failed' }))
        }

        const early = earlySignals
        earlySignals = []
        for (const message of early) {
            receiveQueue = receiveQueue.then(() => onSignal(message)).catch(() => {})
        }

        heartbeatTimer = window.setInterval(() => {
            void callApi
                .heartbeat(id, sessionId)
                .then((inRoom) => {
                    if (!inRoom) void leave('You were disconnected from the call', false)
                })
                .catch(() => {})
        }, HEARTBEAT_MS)
        window.addEventListener('pagehide', onPageHide)
    }

    function onPageHide(): void {
        if (projectId.value !== null && status.value === 'active') {
            callApi.leaveCallOnUnload(projectId.value, sessionId)
        }
    }

    async function leave(reason?: string, tellServer = true): Promise<void> {
        if (status.value === 'idle') return
        const id = projectId.value
        const session = sessionId

        if (heartbeatTimer !== null) window.clearInterval(heartbeatTimer)
        heartbeatTimer = null
        window.removeEventListener('pagehide', onPageHide)
        mesh?.close()
        mesh = null
        local.value.stream?.getTracks().forEach((t) => t.stop())
        cameraTrack?.stop()
        screenTrack?.stop()
        cameraTrack = null
        screenTrack = null

        status.value = 'idle'
        projectId.value = null
        tiles.value = []
        local.value = { stream: null, media: { mic: true, cam: false, screen: false } }
        inviting.value = {}
        minimized.value = false
        sessionId = ''
        earlySignals = []
        if (reason !== undefined) showNotice(reason)

        if (tellServer && id !== null && session !== '') {
            await callApi.leaveCall(id, session).catch(() => {})
        }
    }

    // ---- controls ------------------------------------------------------

    function publishMedia(media: MediaState): void {
        local.value = { ...local.value, media }
        mesh?.setMediaState(media)
    }

    function toggleMic(): void {
        const enabled = !local.value.media.mic
        local.value.stream?.getAudioTracks().forEach((t) => {
            t.enabled = enabled
        })
        publishMedia({ ...local.value.media, mic: enabled })
    }

    /** Local preview: camera or screen, whichever is sent. */
    const localVideo = ref<MediaStream | null>(null)

    function setLocalVideo(track: MediaStreamTrack | null): void {
        localVideo.value = track === null ? null : markRaw(new MediaStream([track]))
    }

    async function toggleCamera(): Promise<void> {
        if (mesh === null) return
        if (cameraTrack !== null) {
            cameraTrack.stop()
            cameraTrack = null
            if (screenTrack === null) {
                await mesh.setVideoTrack(null)
                setLocalVideo(null)
            }
            publishMedia({ ...local.value.media, cam: false })
            return
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true })
            cameraTrack = stream.getVideoTracks()[0] ?? null
        } catch {
            showNotice('Allow camera access in the browser')
            return
        }
        if (screenTrack === null) {
            await mesh.setVideoTrack(cameraTrack)
            setLocalVideo(cameraTrack)
        }
        publishMedia({ ...local.value.media, cam: true })
    }

    async function toggleScreen(): Promise<void> {
        if (mesh === null) return
        if (screenTrack !== null) {
            await stopScreen()
            return
        }
        try {
            const stream = await navigator.mediaDevices.getDisplayMedia({ video: true })
            screenTrack = stream.getVideoTracks()[0] ?? null
        } catch {
            return
        }
        if (screenTrack === null) return
        screenTrack.addEventListener('ended', () => void stopScreen())
        await mesh.setVideoTrack(screenTrack)
        setLocalVideo(screenTrack)
        publishMedia({ ...local.value.media, screen: true })
    }

    async function stopScreen(): Promise<void> {
        if (screenTrack === null) return
        screenTrack.stop()
        screenTrack = null
        await mesh?.setVideoTrack(cameraTrack)
        setLocalVideo(cameraTrack)
        publishMedia({ ...local.value.media, screen: false })
    }

    async function inviteMember(userId: string): Promise<void> {
        const id = projectId.value
        if (id === null) return
        inviting.value = { ...inviting.value, [userId]: 'ringing' }
        try {
            await callApi.inviteToCall(id, userId)
        } catch (e) {
            const rest = { ...inviting.value }
            delete rest[userId]
            inviting.value = rest
            showNotice(e instanceof Error ? e.message : 'Could not invite')
        }
    }

    function setMinimized(value: boolean): void {
        minimized.value = value
    }

    function clearError(): void {
        error.value = null
    }

    return {
        selfId,
        realtimeReady,
        rooms,
        invite,
        notice,
        status,
        projectId,
        projectName,
        members,
        tiles,
        local,
        localVideo,
        error,
        minimized,
        inviting,
        inCall,
        participantsOf,
        isInRoom,
        setSelf,
        setRealtimeReady,
        init,
        reset,
        handleMessage,
        acceptInvite,
        declineInvite,
        open,
        callMember,
        start,
        leave,
        toggleMic,
        toggleCamera,
        toggleScreen,
        inviteMember,
        setMinimized,
        clearError,
    }
})
