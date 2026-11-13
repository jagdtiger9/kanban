<script setup lang="ts">
import { onMounted, ref } from 'vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import { ApiError } from '../api/client'
import { confirmUser, demoteUser, listUsers, promoteUser } from '../api/users'
import type { AdminUserPayload } from '../api/users'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const users = ref<AdminUserPayload[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const busyUserId = ref<string | null>(null)
const demoteTarget = ref<AdminUserPayload | null>(null)

async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
        users.value = await listUsers()
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to load users'
    } finally {
        loading.value = false
    }
}

async function run(user: AdminUserPayload, action: (id: string) => Promise<AdminUserPayload>): Promise<void> {
    busyUserId.value = user.id
    error.value = null
    try {
        const updated = await action(user.id)
        const index = users.value.findIndex((u) => u.id === updated.id)
        if (index >= 0) {
            users.value[index] = updated
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Something went wrong'
    } finally {
        busyUserId.value = null
    }
}

function confirmAccount(user: AdminUserPayload): Promise<void> {
    return run(user, confirmUser)
}

function promote(user: AdminUserPayload): Promise<void> {
    return run(user, promoteUser)
}

async function runDemote(): Promise<void> {
    const user = demoteTarget.value
    demoteTarget.value = null
    if (user) {
        await run(user, demoteUser)
    }
}

onMounted(load)
</script>

<template>
    <main class="flex-1 overflow-y-auto p-6">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <h1 class="text-xl font-semibold">Users</h1>
                <p class="mt-1 text-sm text-muted">Confirm accounts and manage global admins.</p>
            </div>

            <p
                v-if="error"
                class="mb-4 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text"
            >
                {{ error }}
            </p>

            <p v-if="loading" class="text-sm text-faint">Loading…</p>
            <div v-else class="overflow-hidden rounded-xl border border-line">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line bg-panel/60 text-xs uppercase tracking-wider text-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Display name</th>
                            <th class="px-4 py-2 font-medium">Email</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="user in users" :key="user.id" class="transition-colors hover:bg-raised/50">
                            <td class="px-4 py-2.5 text-strong">
                                {{ user.displayName }}
                                <span v-if="user.id === auth.user?.id" class="text-xs text-faint">(you)</span>
                            </td>
                            <td class="px-4 py-2.5 text-muted">{{ user.email }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span
                                        v-if="user.isGlobalAdmin"
                                        class="rounded-full bg-brand/15 px-1.5 py-0.5 text-[10px] font-medium text-brand-text"
                                    >
                                        admin
                                    </span>
                                    <span
                                        v-if="user.confirmed"
                                        class="rounded-full bg-active px-1.5 py-0.5 text-[10px] font-medium text-muted"
                                    >
                                        confirmed
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-warn/15 px-1.5 py-0.5 text-[10px] font-medium text-warn-text"
                                    >
                                        unconfirmed
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <div v-if="user.id !== auth.user?.id" class="flex justify-end gap-2">
                                    <button
                                        v-if="!user.confirmed"
                                        type="button"
                                        :disabled="busyUserId === user.id"
                                        class="cursor-pointer rounded-lg px-2 py-1 text-xs text-brand-text transition-colors hover:bg-brand/10 disabled:cursor-wait disabled:opacity-50"
                                        @click="confirmAccount(user)"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        v-if="!user.isGlobalAdmin"
                                        type="button"
                                        :disabled="busyUserId === user.id"
                                        class="cursor-pointer rounded-lg px-2 py-1 text-xs text-body transition-colors hover:bg-active hover:text-strong disabled:cursor-wait disabled:opacity-50"
                                        @click="promote(user)"
                                    >
                                        Promote
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        :disabled="busyUserId === user.id"
                                        class="cursor-pointer rounded-lg px-2 py-1 text-xs text-danger-text transition-colors hover:bg-danger/10 hover:text-danger-text-hover disabled:cursor-wait disabled:opacity-50"
                                        @click="demoteTarget = user"
                                    >
                                        Demote
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ConfirmDialog
                v-if="demoteTarget"
                title="Demote admin"
                :message="`Remove the global admin role from “${demoteTarget.displayName}”?`"
                confirm-label="Demote"
                :danger="true"
                @confirm="runDemote"
                @cancel="demoteTarget = null"
            />
        </div>
    </main>
</template>
