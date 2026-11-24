<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as projectsApi from '../api/projects'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { relativeTime } from '../utils/dates'
import Modal from './Modal.vue'
import UserAvatar from './UserAvatar.vue'

const props = defineProps<{
    projectId: string
    onlineUserIds: ReadonlySet<string>
    presenceAvailable: boolean | null
}>()

const emit = defineEmits<{ close: [] }>()

const auth = useAuthStore()

const members = ref<MemberPayload[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const selfId = computed(() => auth.user?.id ?? '')
const presenceShown = computed(() => props.presenceAvailable === true)

const sortedMembers = computed(() =>
    [...members.value].sort((a, b) => {
        const online = Number(isOnline(b.userId)) - Number(isOnline(a.userId))
        return online !== 0 ? online : a.displayName.localeCompare(b.displayName)
    }),
)

function isOnline(userId: string): boolean {
    return props.onlineUserIds.has(userId)
}

function lastSeenLabel(member: MemberPayload): string {
    if (member.lastSeenAt === null) return 'Never online'
    const relative = relativeTime(member.lastSeenAt)
    return relative === '' ? 'Last seen unknown' : `Last seen ${relative}`
}

async function loadMembers(): Promise<void> {
    loading.value = true
    try {
        members.value = (await projectsApi.getProject(props.projectId)).members
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to load users'
    } finally {
        loading.value = false
    }
}

onMounted(loadMembers)
</script>

<template>
    <Modal @close="emit('close')">
        <template #title>Users</template>
        <div class="space-y-3">
            <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                {{ error }}
            </p>
            <p v-if="presenceAvailable === null" class="rounded-lg bg-raised px-3 py-2 text-xs text-faint">
                Checking online status…
            </p>
            <p v-else-if="presenceAvailable === false" class="rounded-lg bg-raised px-3 py-2 text-xs text-faint">
                Online status is unavailable. Last seen times are still shown.
            </p>

            <p v-if="loading" class="text-faint">Loading…</p>
            <ul v-else class="divide-y divide-line">
                <li
                    v-for="member in sortedMembers"
                    :key="member.userId"
                    class="flex items-center gap-3 py-2.5">
                    <UserAvatar
                        :user-id="member.userId"
                        :name="member.displayName"
                        :avatar-version="member.avatarVersion"
                        :color="member.avatarColor"
                        size="md"/>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-strong">
                            {{ member.displayName }}
                            <span v-if="member.userId === selfId" class="text-xs text-faint">(you)</span>
                        </p>
                        <p class="text-xs text-faint">{{ member.role === 'admin' ? 'Admin' : 'Member' }}</p>
                    </div>
                    <div class="shrink-0 text-right text-xs">
                        <span
                            v-if="presenceShown && isOnline(member.userId)"
                            class="inline-flex items-center gap-1.5 text-brand-text">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand"/>
                            Online
                        </span>
                        <span v-else class="text-faint">{{ lastSeenLabel(member) }}</span>
                    </div>
                </li>
            </ul>
            <p v-if="!loading && sortedMembers.length === 0" class="text-faint">No members yet.</p>
        </div>
    </Modal>
</template>
