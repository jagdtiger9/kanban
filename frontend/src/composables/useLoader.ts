import { computed, ref, type ComputedRef } from 'vue'

const pendingRequests = ref(0)

export function useLoader(): {
    showFetchLoader: ComputedRef<boolean>
    fetchStart: () => void
    fetchStop: () => void
} {
    const fetchStart = (): void => {
        pendingRequests.value++
    }
    const fetchStop = (): void => {
        if (pendingRequests.value > 0) {
            pendingRequests.value--
        }
    }
    const showFetchLoader: ComputedRef<boolean> = computed(() => pendingRequests.value > 0)

    return {
        showFetchLoader,
        fetchStart,
        fetchStop,
    }
}
