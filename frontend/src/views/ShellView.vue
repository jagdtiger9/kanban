<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, ping } from '../api/client'
import type { ProjectPayload } from '../api/projects'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import MembersDialog from '../components/MembersDialog.vue'
import ProjectCreateDialog from '../components/ProjectCreateDialog.vue'
import RenameProjectDialog from '../components/RenameProjectDialog.vue'
import { useAuthStore } from '../stores/auth'
import { useProjectsStore } from '../stores/projects'
import { useTheme } from '../composables/useTheme'
import { isGlobalAdmin } from '../utils/roles'

type ApiState = 'checking' | 'ok' | 'down'

interface ConfirmState {
    title: string
    message: string
    confirmLabel: string
    danger: boolean
    action: () => Promise<void> | void
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const projects = useProjectsStore()
const { isDark, toggleTheme } = useTheme()

const apiState = ref<ApiState>('checking')
const userInitial = computed(() => (auth.user?.displayName[0] ?? '?').toUpperCase())
const isAdmin = computed(() => isGlobalAdmin(auth.user))

const showCreate = ref(false)
const renameTarget = ref<ProjectPayload | null>(null)
const membersTarget = ref<ProjectPayload | null>(null)
const confirmState = ref<ConfirmState | null>(null)
const actionError = ref<string | null>(null)
const openMenuId = ref<string | null>(null)
const drawerOpen = ref(false)

function onDocumentClick(): void {
    openMenuId.value = null
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') drawerOpen.value = false
}

watch(
    () => route.fullPath,
    () => {
        drawerOpen.value = false
    },
)

onMounted(async () => {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onKeydown)
    projects.fetchProjects()
    try {
        await ping()
        apiState.value = 'ok'
    } catch {
        apiState.value = 'down'
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onKeydown)
})

async function logout(): Promise<void> {
    await auth.logout()
    router.push({ name: 'login' })
}

function onCreated(id: string): void {
    router.push({ name: 'project', params: { id } })
}

function askArchive(project: ProjectPayload): void {
    confirmState.value = {
        title: 'Archive project',
        message: `Archive “${project.name}”? It disappears from the active list and can be restored later.`,
        confirmLabel: 'Archive',
        danger: false,
        action: () => projects.archiveProject(project.id),
    }
}

function askRestore(project: ProjectPayload): void {
    confirmState.value = {
        title: 'Restore project',
        message: `Restore “${project.name}”?`,
        confirmLabel: 'Restore',
        danger: false,
        action: () => projects.restoreProject(project.id),
    }
}

function askDelete(project: ProjectPayload): void {
    confirmState.value = {
        title: 'Delete project',
        message: `Permanently delete “${project.name}”? This cannot be undone.`,
        confirmLabel: 'Delete',
        danger: true,
        action: async () => {
            await projects.deleteProject(project.id)
            if (route.name === 'project' && route.params.id === project.id) {
                router.push({ name: 'board' })
            }
        },
    }
}

async function runConfirm(): Promise<void> {
    const state = confirmState.value
    if (!state) return
    confirmState.value = null
    actionError.value = null
    try {
        await state.action()
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Something went wrong'
    }
}
</script>

<template>
    <div class="flex h-screen flex-col">
        <header class="flex h-14 items-center justify-between border-b border-line bg-panel/60 px-4">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    aria-label="Open menu"
                    aria-controls="app-drawer"
                    :aria-expanded="drawerOpen"
                    class="cursor-pointer rounded-lg border border-line-strong px-2.5 py-1.5 text-sm text-body transition-colors hover:border-line-emphasis hover:text-strong lg:hidden"
                    @click="drawerOpen = !drawerOpen"
                >
                    ☰
                </button>
                <div class="flex size-8 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white">
                    <RouterLink to="/" class="cursor-pointer">C</RouterLink>
                </div>
                <RouterLink to="/" class="cursor-pointer font-semibold">
                    Kanban
                </RouterLink>
            </div>
            <div class="hidden items-center gap-3 lg:flex">
        <span class="flex items-center gap-1.5 text-xs text-muted">
          <span
              class="size-2 rounded-full"
              :class="apiState === 'ok' ? 'bg-emerald-500' : apiState === 'down' ? 'bg-danger' : 'bg-warn'"
          />
          {{ apiState === 'ok' ? 'api ok' : apiState === 'down' ? 'api down' : 'checking api…' }}
        </span>
                <span class="text-sm text-body">{{ auth.user?.displayName }}</span>
                <div class="flex size-8 items-center justify-center rounded-full bg-active text-xs font-semibold text-strong">
                    {{ userInitial }}
                </div>
                <button
                    type="button"
                    :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    :title="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    class="cursor-pointer rounded-lg border border-line-strong px-2.5 py-1.5 text-sm text-body transition-colors hover:border-line-emphasis hover:text-strong"
                    @click="toggleTheme"
                >
                    {{ isDark ? '☀' : '☾' }}
                </button>
                <RouterLink
                    v-if="isAdmin"
                    to="/admin/users"
                    class="cursor-pointer rounded-lg border border-line-strong px-3 py-1.5 text-sm text-body transition-colors hover:border-line-emphasis hover:text-strong"
                >
                    Users
                </RouterLink>
                <RouterLink
                    to="/settings"
                    class="cursor-pointer rounded-lg border border-line-strong px-3 py-1.5 text-sm text-body transition-colors hover:border-line-emphasis hover:text-strong"
                >
                    Settings
                </RouterLink>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-line-strong px-3 py-1.5 text-sm text-body transition-colors hover:border-line-emphasis hover:text-strong"
                    @click="logout"
                >
                    Log out
                </button>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <div
                v-if="drawerOpen"
                class="fixed inset-0 z-30 bg-black/60 lg:hidden"
                @click="drawerOpen = false"
            />
            <aside
                id="app-drawer"
                class="fixed inset-y-0 left-0 z-40 flex w-72 max-w-[calc(100vw-3rem)] shrink-0 flex-col overflow-y-auto border-r border-line bg-panel p-4 transition-transform duration-200 lg:static lg:z-auto lg:w-60 lg:max-w-none lg:translate-x-0 lg:bg-panel/40"
                :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="mb-4 flex items-center justify-between lg:hidden">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-faint">Menu</h2>
                    <button
                        type="button"
                        aria-label="Close menu"
                        class="cursor-pointer rounded-lg p-1 text-muted transition-colors hover:bg-raised hover:text-strong"
                        @click="drawerOpen = false"
                    >
                        ✕
                    </button>
                </div>

                <div class="mb-4 flex items-center gap-2 border-b border-line pb-3 lg:hidden">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-active text-xs font-semibold text-strong">
                        {{ userInitial }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-strong">{{ auth.user?.displayName }}</p>
                        <span class="flex items-center gap-1.5 text-xs text-muted">
                            <span
                                class="size-2 rounded-full"
                                :class="apiState === 'ok' ? 'bg-emerald-500' : apiState === 'down' ? 'bg-danger' : 'bg-warn'"
                            />
                            {{ apiState === 'ok' ? 'api ok' : apiState === 'down' ? 'api down' : 'checking api…' }}
                        </span>
                    </div>
                </div>

                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-faint">Projects</h2>
                    <button
                        type="button"
                        aria-label="New project"
                        class="cursor-pointer rounded-lg px-2 py-0.5 text-sm font-semibold text-brand-text transition-colors hover:bg-raised"
                        @click="showCreate = true"
                    >
                        + New
                    </button>
                </div>

                <p
                    v-if="actionError"
                    class="mb-3 rounded-lg border border-danger/40 bg-danger/10 px-2 py-1.5 text-xs text-danger-text"
                >
                    {{ actionError }}
                </p>

                <p
                    v-if="projects.error"
                    class="mb-3 rounded-lg border border-danger/40 bg-danger/10 px-2 py-1.5 text-xs text-danger-text"
                >
                    {{ projects.error }}
                    <button
                        type="button"
                        class="mt-1 block cursor-pointer font-medium underline"
                        @click="projects.fetchProjects()"
                    >
                        Retry
                    </button>
                </p>

                <p v-else-if="!projects.initialized" class="text-sm text-faint">Loading…</p>

                <ul v-else-if="projects.activeProjects.length > 0" class="space-y-0.5">
                    <li v-for="project in projects.activeProjects" :key="project.id" class="relative">
                        <div class="group flex items-center rounded-lg transition-colors hover:bg-raised">
                            <RouterLink
                                :to="{ name: 'project', params: { id: project.id } }"
                                class="min-w-0 flex-1 truncate rounded-lg px-2 py-1.5 text-sm text-body transition-colors hover:text-strong"
                            >
                                {{ project.name }}
                            </RouterLink>
                            <span
                                v-if="project.role === 'admin'"
                                class="mr-1 rounded-full bg-brand/15 px-1.5 py-0.5 text-[10px] font-medium text-brand-text"
                            >
                admin
              </span>
                            <button
                                type="button"
                                aria-label="Project actions"
                                class="cursor-pointer rounded-lg px-1.5 py-1 text-faint opacity-100 transition-opacity hover:text-strong focus-visible:opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                                @click.stop="openMenuId = openMenuId === project.id ? null : project.id"
                            >
                                ⋯
                            </button>
                        </div>
                        <div
                            v-if="openMenuId === project.id"
                            class="absolute right-0 top-full z-10 mt-1 w-36 rounded-lg border border-line-strong bg-raised py-1 shadow-xl"
                        >
                            <button
                                type="button"
                                class="block w-full cursor-pointer px-3 py-1.5 text-left text-sm text-body transition-colors hover:bg-active hover:text-strong"
                                @click="membersTarget = project"
                            >
                                Members
                            </button>
                            <button
                                type="button"
                                class="block w-full cursor-pointer px-3 py-1.5 text-left text-sm text-body transition-colors hover:bg-active hover:text-strong"
                                @click="renameTarget = project"
                            >
                                Rename
                            </button>
                            <button
                                type="button"
                                class="block w-full cursor-pointer px-3 py-1.5 text-left text-sm text-body transition-colors hover:bg-active hover:text-strong"
                                @click="askArchive(project)"
                            >
                                Archive
                            </button>
                            <button
                                v-if="isAdmin"
                                type="button"
                                class="block w-full cursor-pointer px-3 py-1.5 text-left text-sm text-danger-text transition-colors hover:bg-danger/10 hover:text-danger-text-hover"
                                @click="askDelete(project)"
                            >
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>
                <div v-else class="rounded-lg border border-dashed border-line-strong px-3 py-4 text-center">
                    <p class="text-sm text-faint">No projects yet.</p>
                    <button
                        type="button"
                        class="mt-1 cursor-pointer text-sm font-medium text-brand-text transition-colors hover:text-brand-text-hover"
                        @click="showCreate = true"
                    >
                        Create your first project
                    </button>
                </div>

                <template v-if="projects.archivedProjects.length > 0">
                    <h2 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-faint">Archived</h2>
                    <ul class="space-y-0.5">
                        <li
                            v-for="project in projects.archivedProjects"
                            :key="project.id"
                            class="flex items-center rounded-lg px-2 py-1.5 hover:bg-raised"
                        >
                            <span class="min-w-0 flex-1 truncate text-sm text-faint">{{ project.name }}</span>
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg px-1.5 py-0.5 text-xs text-muted transition-colors hover:text-strong"
                                @click="askRestore(project)"
                            >
                                Restore
                            </button>
                        </li>
                    </ul>
                </template>

                <div class="mt-auto space-y-1 border-t border-line pt-4 lg:hidden">
                    <RouterLink
                        to="/settings"
                        class="block cursor-pointer rounded-lg px-2 py-2 text-sm text-body transition-colors hover:bg-raised hover:text-strong"
                    >
                        Settings
                    </RouterLink>
                    <RouterLink
                        v-if="isAdmin"
                        to="/admin/users"
                        class="block cursor-pointer rounded-lg px-2 py-2 text-sm text-body transition-colors hover:bg-raised hover:text-strong"
                    >
                        Users
                    </RouterLink>
                    <button
                        type="button"
                        class="block w-full cursor-pointer rounded-lg px-2 py-2 text-left text-sm text-body transition-colors hover:bg-raised hover:text-strong"
                        @click="toggleTheme"
                    >
                        {{ isDark ? '☀ Light theme' : '☾ Dark theme' }}
                    </button>
                    <button
                        type="button"
                        class="block w-full cursor-pointer rounded-lg px-2 py-2 text-left text-sm text-body transition-colors hover:bg-raised hover:text-strong"
                        @click="logout"
                    >
                        Log out
                    </button>
                </div>
            </aside>

            <RouterView/>
        </div>

        <ProjectCreateDialog v-if="showCreate" @close="showCreate = false" @created="onCreated"/>
        <RenameProjectDialog
            v-if="renameTarget"
            :project-id="renameTarget.id"
            :initial-name="renameTarget.name"
            @close="renameTarget = null"
        />
        <MembersDialog v-if="membersTarget" :project-id="membersTarget.id" @close="membersTarget = null"/>
        <ConfirmDialog
            v-if="confirmState"
            :title="confirmState.title"
            :message="confirmState.message"
            :confirm-label="confirmState.confirmLabel"
            :danger="confirmState.danger"
            @confirm="runConfirm"
            @cancel="confirmState = null"
        />
    </div>
</template>
