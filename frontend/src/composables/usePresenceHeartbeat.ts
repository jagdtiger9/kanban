import { onBeforeUnmount, onMounted, type Ref } from 'vue'
import { touchProjectPresence, touchProjectPresenceOnUnload } from '../api/projects'

const HEARTBEAT_INTERVAL_MS = 5 * 60 * 1000

/**
 * Persists the member's last-seen time while the board is open. Centrifugo
 * presence covers live online state; this heartbeat only feeds "last seen".
 */
export function usePresenceHeartbeat(projectId: Ref<string>): void {
    let timer: ReturnType<typeof setInterval> | null = null

    function beat(): void {
        void touchProjectPresence(projectId.value).catch(() => undefined)
    }

    function onPageHide(): void {
        touchProjectPresenceOnUnload(projectId.value)
    }

    onMounted(() => {
        beat()
        timer = setInterval(beat, HEARTBEAT_INTERVAL_MS)
        window.addEventListener('pagehide', onPageHide)
    })

    onBeforeUnmount(() => {
        if (timer !== null) clearInterval(timer)
        window.removeEventListener('pagehide', onPageHide)
        // In-page navigation does not fire pagehide, so record the departure
        // explicitly; a beacon also survives the unload that may follow.
        onPageHide()
    })
}
