import { ref } from 'vue'
import { defineStore } from 'pinia'
import * as userEventsApi from '../api/userEvents'
import type { UserEventItem } from '../api/userEvents'

export const useUserEventsStore = defineStore('userEvents', () => {
    const items = ref<UserEventItem[]>([])
    const unreadCount = ref(0)
    const cursor = ref<string | null>(null)
    const loading = ref(false)
    const initialized = ref(false)
    const error = ref<string | null>(null)

    async function load(): Promise<void> {
        if (loading.value) return
        loading.value = true
        error.value = null
        try {
            const [page, unread] = await Promise.all([
                userEventsApi.listUserEvents(),
                userEventsApi.getUnreadUserEventCount(),
            ])
            items.value = page.items
            cursor.value = page.nextCursor
            unreadCount.value = unread.count
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load notifications'
        } finally {
            loading.value = false
            initialized.value = true
        }
    }

    async function loadMore(): Promise<void> {
        if (cursor.value === null || loading.value) return
        loading.value = true
        error.value = null
        try {
            const page = await userEventsApi.listUserEvents(cursor.value)
            const known = new Set(items.value.map((item) => item.id))
            items.value.push(...page.items.filter((item) => !known.has(item.id)))
            cursor.value = page.nextCursor
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load more notifications'
        } finally {
            loading.value = false
        }
    }

    function prepend(item: UserEventItem): void {
        if (items.value.some((existing) => existing.id === item.id)) return
        items.value.unshift(item)
        unreadCount.value += 1
    }

    async function markSeen(): Promise<void> {
        if (unreadCount.value === 0) return
        unreadCount.value = 0
        const stamp = new Date().toISOString()
        for (const item of items.value) {
            if (item.seenAt === null) item.seenAt = stamp
        }
        try {
            await userEventsApi.markUserEventsSeen()
        } catch {
            // Best-effort: the next load restores the server-side count.
        }
    }

    function reset(): void {
        items.value = []
        unreadCount.value = 0
        cursor.value = null
        loading.value = false
        initialized.value = false
        error.value = null
    }

    return {
        items,
        unreadCount,
        cursor,
        loading,
        initialized,
        error,
        load,
        loadMore,
        prepend,
        markSeen,
        reset,
    }
})
