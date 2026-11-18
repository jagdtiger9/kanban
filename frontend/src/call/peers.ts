import type { MediaState, SignalKind } from './types'

export type PeerStatus = 'connecting' | 'connected' | 'failed'

export interface PeerCallbacks {
    sendSignal: (toUserId: string, toSessionId: string, kind: SignalKind, data: Record<string, unknown>) => void
    onStream: (userId: string, stream: MediaStream) => void
    onStatus: (userId: string, status: PeerStatus) => void
    onMediaState: (userId: string, state: MediaState) => void
}

interface Peer {
    sessionId: string
    pc: RTCPeerConnection
    stream: MediaStream
    pendingIce: RTCIceCandidateInit[]
    failTimer: number
}

const CONNECT_TIMEOUT_MS = 15000

/** Connection diagnostics in the browser console, filter by "[call]". */
function log(userId: string, ...parts: unknown[]): void {
    console.info(`[call] ${userId.slice(0, 8)}`, ...parts)
}

/** "relay udp 192.168.1.33:49170" from an ICE candidate line. */
function describeCandidate(line: string): string {
    const f = line.split(' ')
    return `${f[7] ?? '?'} ${f[2] ?? '?'} ${f[4] ?? '?'}:${f[5] ?? '?'}`
}

/**
 * Mesh of RTCPeerConnections, one per other participant. Not reactive: the
 * store mirrors what the UI needs through the callbacks.
 *
 * Every connection carries one audio and one video transceiver from the
 * start. Camera, screen share and "camera off" only swap the video track
 * (replaceTrack), so there is never a renegotiation after the first
 * offer/answer. The participant who joined later sends the offer.
 */
export class PeerMesh {
    private peers = new Map<string, Peer>()
    private videoTrack: MediaStreamTrack | null = null
    private mediaState: MediaState = { mic: true, cam: false, screen: false }

    constructor(
        private readonly iceServers: RTCIceServer[],
        private readonly localStream: MediaStream,
        private readonly callbacks: PeerCallbacks,
    ) {
        console.info('[call] ICE servers', iceServers.map((s) => s.urls))
    }

    async connect(userId: string, sessionId: string): Promise<void> {
        const peer = this.createPeer(userId, sessionId)
        const audio = this.localStream.getAudioTracks()[0] ?? null
        peer.pc.addTransceiver(audio ?? 'audio', { direction: 'sendrecv' })
        peer.pc.addTransceiver(this.videoTrack ?? 'video', { direction: 'sendrecv' })

        const offer = await peer.pc.createOffer()
        await peer.pc.setLocalDescription(offer)
        log(userId, 'offer sent')
        this.callbacks.sendSignal(userId, sessionId, 'offer', { sdp: offer.sdp ?? '' })
    }

    async handleSignal(userId: string, sessionId: string, kind: SignalKind, data: Record<string, unknown>): Promise<void> {
        if (kind === 'offer') {
            await this.acceptOffer(userId, sessionId, String(data.sdp ?? ''))
            return
        }

        const peer = this.peers.get(userId)
        if (peer === undefined || peer.sessionId !== sessionId) return

        if (kind === 'answer') {
            log(userId, 'answer received')
            await peer.pc.setRemoteDescription({ type: 'answer', sdp: String(data.sdp ?? '') })
            await this.flushIce(peer)
        } else if (kind === 'ice') {
            const candidate = data.candidate as RTCIceCandidateInit | undefined
            if (candidate === undefined) return
            log(userId, 'remote candidate', describeCandidate(candidate.candidate ?? ''))
            if (peer.pc.remoteDescription === null) {
                peer.pendingIce.push(candidate)
            } else {
                await peer.pc.addIceCandidate(candidate).catch(() => {})
            }
        } else if (kind === 'state') {
            this.callbacks.onMediaState(userId, {
                mic: data.mic === true,
                cam: data.cam === true,
                screen: data.screen === true,
            })
        }
    }

    has(userId: string): boolean {
        return this.peers.has(userId)
    }

    remove(userId: string): void {
        const peer = this.peers.get(userId)
        if (peer === undefined) return
        window.clearTimeout(peer.failTimer)
        peer.pc.close()
        this.peers.delete(userId)
    }

    /** Camera, screen, or null for "no video". */
    async setVideoTrack(track: MediaStreamTrack | null): Promise<void> {
        this.videoTrack = track
        const jobs: Promise<void>[] = []
        for (const peer of this.peers.values()) {
            const sender = this.videoSender(peer.pc)
            if (sender !== null) jobs.push(sender.replaceTrack(track))
        }
        await Promise.all(jobs)
    }

    setMediaState(state: MediaState): void {
        this.mediaState = state
        for (const [userId, peer] of this.peers) {
            this.sendState(userId, peer)
        }
    }

    close(): void {
        for (const userId of [...this.peers.keys()]) {
            this.remove(userId)
        }
    }

    private async acceptOffer(userId: string, sessionId: string, sdp: string): Promise<void> {
        // A new offer from the same user (they rejoined) replaces the old connection.
        log(userId, 'offer received')
        this.remove(userId)
        const peer = this.createPeer(userId, sessionId)
        await peer.pc.setRemoteDescription({ type: 'offer', sdp })

        const audio = this.localStream.getAudioTracks()[0] ?? null
        for (const transceiver of peer.pc.getTransceivers()) {
            const kind = transceiver.receiver.track.kind
            transceiver.direction = 'sendrecv'
            await transceiver.sender.replaceTrack(kind === 'audio' ? audio : this.videoTrack)
        }

        const answer = await peer.pc.createAnswer()
        await peer.pc.setLocalDescription(answer)
        log(userId, 'answer sent')
        this.callbacks.sendSignal(userId, sessionId, 'answer', { sdp: answer.sdp ?? '' })
        await this.flushIce(peer)
    }

    private createPeer(userId: string, sessionId: string): Peer {
        const pc = new RTCPeerConnection({ iceServers: this.iceServers })
        const peer: Peer = {
            sessionId,
            pc,
            stream: new MediaStream(),
            pendingIce: [],
            failTimer: window.setTimeout(() => {
                if (pc.connectionState === 'connected') return
                log(userId, `no connection after ${CONNECT_TIMEOUT_MS / 1000} s, state ${pc.connectionState}`)
                this.callbacks.onStatus(userId, 'failed')
            }, CONNECT_TIMEOUT_MS),
        }
        this.peers.set(userId, peer)
        this.callbacks.onStatus(userId, 'connecting')
        this.callbacks.onStream(userId, peer.stream)

        pc.onicecandidate = (event) => {
            if (event.candidate !== null) {
                log(userId, 'local candidate', describeCandidate(event.candidate.candidate))
                this.callbacks.sendSignal(userId, sessionId, 'ice', { candidate: event.candidate.toJSON() })
            }
        }
        pc.onicecandidateerror = (event) => {
            log(userId, 'candidate error', event.url, event.errorCode, event.errorText)
        }
        pc.onicegatheringstatechange = () => log(userId, 'gathering', pc.iceGatheringState)
        pc.oniceconnectionstatechange = () => log(userId, 'ice', pc.iceConnectionState)
        pc.ontrack = (event) => {
            log(userId, 'track', event.track.kind)
            peer.stream.addTrack(event.track)
            this.callbacks.onStream(userId, peer.stream)
        }
        pc.onconnectionstatechange = () => {
            if (this.peers.get(userId) !== peer) return
            log(userId, 'connection', pc.connectionState)
            if (pc.connectionState === 'connected') {
                void this.logRoute(userId, pc)
                window.clearTimeout(peer.failTimer)
                this.callbacks.onStatus(userId, 'connected')
                this.sendState(userId, peer)
            } else if (pc.connectionState === 'failed') {
                this.callbacks.onStatus(userId, 'failed')
            }
        }

        return peer
    }

    /** The candidate pair in use: direct in the LAN, via STUN, or through TURN. */
    private async logRoute(userId: string, pc: RTCPeerConnection): Promise<void> {
        const stats = await pc.getStats().catch(() => null)
        if (stats === null) return
        const all = new Map<string, Record<string, unknown>>()
        stats.forEach((s: Record<string, unknown>) => all.set(String(s.id), s))
        for (const s of all.values()) {
            if (s.type !== 'transport' || typeof s.selectedCandidatePairId !== 'string') continue
            const pair = all.get(s.selectedCandidatePairId)
            const local = all.get(String(pair?.localCandidateId))
            const remote = all.get(String(pair?.remoteCandidateId))
            const side = (c: Record<string, unknown> | undefined) =>
                `${String(c?.candidateType)} ${String(c?.protocol)} ${String(c?.address ?? c?.ip)}:${String(c?.port)}`
            log(userId, 'route', side(local), '<->', side(remote))
        }
    }

    private async flushIce(peer: Peer): Promise<void> {
        const pending = peer.pendingIce.splice(0)
        for (const candidate of pending) {
            await peer.pc.addIceCandidate(candidate).catch(() => {})
        }
    }

    private sendState(userId: string, peer: Peer): void {
        this.callbacks.sendSignal(userId, peer.sessionId, 'state', { ...this.mediaState })
    }

    private videoSender(pc: RTCPeerConnection): RTCRtpSender | null {
        for (const transceiver of pc.getTransceivers()) {
            if (transceiver.receiver.track.kind === 'video') return transceiver.sender
        }
        return null
    }
}
