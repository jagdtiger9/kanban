<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import * as projectsApi from '../api/projects'
import type { MemberPayload, MemberRole } from '../api/projects'
import { searchUsers } from '../api/users'
import type { UserSuggestion } from '../api/users'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import Modal from './Modal.vue'

const props = defineProps<{ projectId: string }>()

const emit = defineEmits<{ close: [] }>()

const auth = useAuthStore()

const members = ref<MemberPayload[]>([])
const myRole = ref<MemberRole | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const searchTerm = ref('')
const suggestions = ref<UserSuggestion[]>([])
const selectedUser = ref<UserSuggestion | null>(null)
const searching = ref(false)
const busyUserId = ref<string | null>(null)
let debounceTimer: ReturnType<typeof setTimeout> | undefined

const isAdmin = computed(() => myRole.value === 'admin')
const selfId = computed(() => auth.user?.id ?? '')

const roleOptions: { label: string; value: MemberRole }[] = [
    { label: 'Member', value: 'member' },
    { label: 'Admin', value: 'admin' },
]

async function loadMembers(): Promise<void> {
    loading.value = true
    try {
        const detail = await projectsApi.getProject(props.projectId)
        members.value = detail.members
        myRole.value = detail.project.role ?? null
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to load members'
    } finally {
        loading.value = false
    }
}

function onSearchInput(): void {
    selectedUser.value = null
    clearTimeout(debounceTimer)
    const term = searchTerm.value.trim()
    if (term === '') {
        suggestions.value = []
        return
    }
    debounceTimer = setTimeout(async () => {
        searching.value = true
        try {
            const results = await searchUsers(term)
            const memberIds = new Set(members.value.map((m) => m.userId))
            suggestions.value = results.filter((u) => !memberIds.has(u.id))
        } catch {
            suggestions.value = []
        } finally {
            searching.value = false
        }
    }, 300)
}

function selectUser(user: UserSuggestion): void {
    selectedUser.value = user
    searchTerm.value = user.displayName
    suggestions.value = []
}

async function addMember(): Promise<void> {
    const user = selectedUser.value
    if (user === null) {
        return
    }
    busyUserId.value = user.id
    error.value = null
    try {
        const added = await projectsApi.addMember(props.projectId, user.id, 'member')
        members.value.push({
            ...added,
            email: user.email,
            displayName: user.displayName,
            avatarVersion: user.avatarVersion,
            avatarColor: user.avatarColor,
            lastSeenAt: null,
        })
        searchTerm.value = ''
        suggestions.value = []
        selectedUser.value = null
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to add member'
    } finally {
        busyUserId.value = null
    }
}

async function changeRole(member: MemberPayload, role: MemberRole): Promise<void> {
    busyUserId.value = member.userId
    error.value = null
    try {
        const updated = await projectsApi.changeMemberRole(props.projectId, member.userId, role)
        const index = members.value.findIndex((m) => m.userId === updated.userId)
        if (index >= 0) {
            members.value[index] = { ...members.value[index], role: updated.role }
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to change role'
        loadMembers()
    } finally {
        busyUserId.value = null
    }
}

async function removeMember(member: MemberPayload): Promise<void> {
    busyUserId.value = member.userId
    error.value = null
    try {
        await projectsApi.removeMember(props.projectId, member.userId)
        members.value = members.value.filter((m) => m.userId !== member.userId)
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to remove member'
    } finally {
        busyUserId.value = null
    }
}

onMounted(loadMembers)
onUnmounted(() => clearTimeout(debounceTimer))
</script>

<template>
    <Modal @close="emit('close')">
        <template #title>Members</template>
        <div class="space-y-4">
            <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                {{ error }}
            </p>

            <div v-if="isAdmin">
                <label for="member-search" class="mb-1 block font-medium text-body">Add member</label>
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <InputText
                            id="member-search"
                            v-model="searchTerm"
                            placeholder="Search by email or name…"
                            autocomplete="off"
                            class="w-full"
                            @input="onSearchInput"
                        />
                        <ul v-if="suggestions.length > 0" class="mt-1 overflow-hidden rounded-lg border border-line-strong">
                            <li v-for="user in suggestions" :key="user.id">
                                <Button
                                    severity="secondary"
                                    text
                                    fluid
                                    class="justify-between px-3 py-2 text-left"
                                    @click="selectUser(user)"
                                >
                                    <span class="text-strong">{{ user.displayName }}</span>
                                    <span class="text-xs text-muted">{{ user.email }}</span>
                                </Button>
                            </li>
                        </ul>
                        <p v-else-if="searching" class="mt-1 text-xs text-faint">Searching…</p>
                    </div>
                    <Button
                        label="Add"
                        size="small"
                        :disabled="selectedUser === null || busyUserId !== null"
                        :loading="selectedUser !== null && busyUserId === selectedUser.id"
                        @click="addMember"
                    />
                </div>
                <p v-if="selectedUser" class="mt-1 text-xs text-muted">
                    Add <span class="text-strong">{{ selectedUser.displayName }}</span> ({{ selectedUser.email }}) as a member?
                </p>
            </div>

            <div>
                <p v-if="loading" class="text-faint">Loading…</p>
                <ul v-else class="divide-y divide-line">
                    <li v-for="member in members" :key="member.userId" class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-strong">
                                {{ member.displayName }}
                                <span v-if="member.userId === selfId" class="text-xs text-faint">(you)</span>
                            </p>
                            <p class="truncate text-xs text-faint">{{ member.email }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Select
                                :model-value="member.role"
                                :options="roleOptions"
                                option-label="label"
                                option-value="value"
                                size="small"
                                :disabled="!isAdmin || member.userId === selfId || busyUserId === member.userId"
                                @update:model-value="(role: MemberRole) => changeRole(member, role)"
                            />
                            <Button
                                v-if="isAdmin && member.userId !== selfId"
                                severity="danger"
                                text
                                size="small"
                                :disabled="busyUserId === member.userId"
                                label="Remove"
                                @click="removeMember(member)"
                            />
                        </div>
                    </li>
                </ul>
                <p v-if="!loading && members.length === 0" class="text-faint">No members yet.</p>
            </div>
        </div>
    </Modal>
</template>
