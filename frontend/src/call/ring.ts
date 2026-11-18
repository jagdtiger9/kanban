/**
 * Standard ring: two short beeps every 2 s, generated with Web Audio (no
 * sound file). Browsers may block audio until the user has interacted with
 * the page — then the ring is silent and only the popup shows.
 */
export function startRing(): () => void {
    const AudioCtx = window.AudioContext
    if (AudioCtx === undefined) return () => {}

    let ctx: AudioContext | null = null
    try {
        ctx = new AudioCtx()
    } catch {
        return () => {}
    }
    const audio = ctx

    function beep(at: number): void {
        const osc = audio.createOscillator()
        const gain = audio.createGain()
        osc.frequency.value = 440
        gain.gain.setValueAtTime(0.0001, at)
        gain.gain.exponentialRampToValueAtTime(0.2, at + 0.02)
        gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.35)
        osc.connect(gain).connect(audio.destination)
        osc.start(at)
        osc.stop(at + 0.4)
    }

    function cycle(): void {
        const now = audio.currentTime
        beep(now)
        beep(now + 0.5)
    }

    void audio.resume().catch(() => {})
    cycle()
    const timer = window.setInterval(cycle, 2000)

    return () => {
        window.clearInterval(timer)
        void audio.close().catch(() => {})
    }
}
