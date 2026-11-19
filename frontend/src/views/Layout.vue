<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { ApiError, ping } from '../api/client'
import type { ProjectPayload } from '../api/projects'
import MembersDialog from '../components/MembersDialog.vue'
import CallLayer from '../call/CallLayer.vue'
import ProjectCreateDialog from '../components/ProjectCreateDialog.vue'
import ProjectListItem from '../components/ProjectListItem.vue'
import RenameProjectDialog from '../components/RenameProjectDialog.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { useAuthStore } from '../stores/auth'
import { useProjectsStore } from '../stores/projects'
import { useTheme } from '../composables/useTheme'
import { isGlobalAdmin } from '../utils/roles'

type ApiState = 'checking' | 'ok' | 'down'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const projects = useProjectsStore()
const confirm = useConfirm()
const { isDark, toggleTheme } = useTheme()

const apiState = ref<ApiState>('checking')
const isAdmin = computed(() => isGlobalAdmin(auth.user))

const showCreate = ref(false)
const renameTarget = ref<ProjectPayload | null>(null)
const membersTarget = ref<ProjectPayload | null>(null)
const actionError = ref<string | null>(null)
const projectsCollapsed = ref(true)
const drawerOpen = ref(false)

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') drawerOpen.value = false
}

watch(
    () => route.fullPath,
    () => {
        drawerOpen.value = false
    },
)

watch(membersTarget, (target) => {
    if (target === null) {
        projects.fetchProjects()
    }
})

onMounted(async () => {
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
    document.removeEventListener('keydown', onKeydown)
})

async function logout(): Promise<void> {
    await auth.logout()
    router.push({ name: 'login' })
}

function onCreated(id: string): void {
    router.push({ name: 'project', params: { id } })
}

interface ConfirmOptions {
    header: string
    message: string
    acceptLabel: string
    danger: boolean
    action: () => Promise<void> | void
}

function ask(options: ConfirmOptions): void {
    confirm.require({
        header: options.header,
        message: options.message,
        acceptProps: { label: options.acceptLabel, severity: options.danger ? 'danger' : undefined },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: async () => {
            actionError.value = null
            try {
                await options.action()
            } catch (e) {
                actionError.value = e instanceof ApiError ? e.message : 'Something went wrong'
            }
        },
    })
}

function askArchive(project: ProjectPayload): void {
    ask({
        header: 'Archive project',
        message: `Archive “${project.name}”? It disappears from the active list and can be restored later.`,
        acceptLabel: 'Archive',
        danger: false,
        action: () => projects.archiveProject(project.id),
    })
}

function askRestore(project: ProjectPayload): void {
    ask({
        header: 'Restore project',
        message: `Restore “${project.name}”?`,
        acceptLabel: 'Restore',
        danger: false,
        action: () => projects.restoreProject(project.id),
    })
}

function askDelete(project: ProjectPayload): void {
    ask({
        header: 'Delete project',
        message: `Permanently delete “${project.name}”? This cannot be undone.`,
        acceptLabel: 'Delete',
        danger: true,
        action: async () => {
            await projects.deleteProject(project.id)
            if (route.name === 'project' && route.params.id === project.id) {
                router.push({ name: 'board' })
            }
        },
    })
}

async function onFavourite(project: ProjectPayload, isFavourite: boolean): Promise<void> {
    actionError.value = null
    try {
        await projects.toggleFavourite(project.id, isFavourite)
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Something went wrong'
    }
}
</script>

<template>
    <div class="flex h-screen flex-col">
        <header class="flex h-14 items-center justify-between border-b border-line bg-panel/60 px-4">
            <div class="flex items-center gap-3">
                <Button
                    aria-label="Open menu"
                    aria-controls="app-drawer"
                    :aria-expanded="drawerOpen"
                    severity="secondary"
                    outlined
                    size="small"
                    class="lg:hidden"
                    label="☰"
                    @click="drawerOpen = !drawerOpen"
                />
                <div class="flex size-8 items-center justify-center rounded-lg bg-brand font-bold text-white">
                    <RouterLink to="/">C</RouterLink>
                </div>
                <RouterLink to="/" class="font-semibold">
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
                <span class="text-body">{{ auth.user?.displayName }}</span>
                <UserAvatar
                    :user-id="auth.user?.id ?? ''"
                    :name="auth.user?.displayName ?? null"
                    :avatar-version="auth.user?.avatarVersion ?? null"
                    size="md"
                />
                <Button
                    :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    :title="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    severity="secondary"
                    outlined
                    size="small"
                    :label="isDark ? '☀' : '☾'"
                    @click="toggleTheme"
                />
                <RouterLink
                    v-if="isAdmin"
                    to="/admin/users"
                    class="rounded-lg border border-line-strong px-3 py-1.5 text-body transition-colors hover:border-line-emphasis hover:text-strong"
                >
                    Users
                </RouterLink>
                <RouterLink
                    to="/settings"
                    class="rounded-lg border border-line-strong px-3 py-1.5 text-body transition-colors hover:border-line-emphasis hover:text-strong"
                >
                    Settings
                </RouterLink>
                <Button severity="secondary" size="small" label="Log out" @click="logout"/>
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
                    <Button
                        aria-label="Close menu"
                        severity="secondary"
                        text
                        size="small"
                        label="✕"
                        @click="drawerOpen = false"
                    />
                </div>

                <div class="mb-4 flex items-center gap-2 border-b border-line pb-3 lg:hidden">
                    <UserAvatar
                        :user-id="auth.user?.id ?? ''"
                        :name="auth.user?.displayName ?? null"
                        :avatar-version="auth.user?.avatarVersion ?? null"
                        size="md"
                    />
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ auth.user?.displayName }}</p>
                        <span class="flex items-center gap-1.5 text-xs text-muted">
                            <span
                                class="size-2 rounded-full"
                                :class="apiState === 'ok' ? 'bg-emerald-500' : apiState === 'down' ? 'bg-danger' : 'bg-warn'"
                            />
                            {{ apiState === 'ok' ? 'api ok' : apiState === 'down' ? 'api down' : 'checking api…' }}
                        </span>
                    </div>
                </div>

                <ul v-if="projects.favouriteProjects.length > 0" class="mb-3 space-y-0.5">
                    <ProjectListItem
                        v-for="project in projects.favouriteProjects"
                        :key="project.id"
                        :project="project"
                        :can-delete="isAdmin"
                        @favourite="onFavourite(project, $event)"
                        @members="membersTarget = project"
                        @rename="renameTarget = project"
                        @archive="askArchive(project)"
                        @delete="askDelete(project)"
                    />
                </ul>

                <div class="mb-3 flex items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-1 text-xs font-semibold uppercase tracking-wider text-faint transition-colors hover:text-body"
                        :aria-expanded="!projectsCollapsed"
                        aria-controls="other-projects"
                        @click="projectsCollapsed = !projectsCollapsed">
                        <span class="text-[10px]">{{ projectsCollapsed ? '▸' : '▾' }}</span>
                        Projects
                    </button>
                    <Button
                        aria-label="New project"
                        text
                        size="small"
                        label="+ New"
                        @click="showCreate = true"
                    />
                </div>

                <p v-if="actionError"
                   class="mb-3 rounded-lg border border-danger/40 bg-danger/10 px-2 py-1.5 text-xs text-danger-text">
                    {{ actionError }}
                </p>

                <p v-if="projects.error"
                   class="mb-3 rounded-lg border border-danger/40 bg-danger/10 px-2 py-1.5 text-xs text-danger-text">
                    {{ projects.error }}
                    <Button
                        severity="secondary"
                        text
                        size="small"
                        class="mt-1"
                        label="Retry"
                        @click="projects.fetchProjects()"/>
                </p>
                <p v-else-if="!projects.initialized" class="text-faint">Loading…</p>
                <template v-else>
                    <ul v-show="!projectsCollapsed" id="other-projects" class="space-y-0.5">
                        <ProjectListItem
                            v-for="project in projects.otherProjects"
                            :key="project.id"
                            :project="project"
                            :can-delete="isAdmin"
                            @favourite="onFavourite(project, $event)"
                            @members="membersTarget = project"
                            @rename="renameTarget = project"
                            @archive="askArchive(project)"
                            @delete="askDelete(project)"/>
                    </ul>
                    <div v-if="projects.activeProjects.length === 0"
                         class="rounded-lg border border-dashed border-line-strong px-3 py-4 text-center">
                        <p class="text-faint">No projects yet.</p>
                        <Button
                            text
                            size="small"
                            class="mt-1"
                            label="Create your first project"
                            @click="showCreate = true"/>
                    </div>
                </template>

                <template v-if="projects.archivedProjects.length > 0">
                    <h2 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-faint">Archived</h2>
                    <ul class="space-y-0.5">
                        <li v-for="project in projects.archivedProjects"
                            :key="project.id"
                            class="flex items-center rounded-lg px-2 py-1.5 hover:bg-raised">
                            <span class="min-w-0 flex-1 truncate text-faint">{{ project.name }}</span>
                            <Button
                                severity="secondary"
                                text
                                size="small"
                                label="Restore"
                                @click="askRestore(project)"/>
                        </li>
                    </ul>
                </template>

                <div class="mt-auto space-y-1 border-t border-line pt-4 lg:hidden">
                    <RouterLink
                        to="/settings"
                        class="block rounded-lg px-2 py-2 text-body transition-colors hover:bg-raised hover:text-strong"
                    >
                        Settings
                    </RouterLink>
                    <RouterLink
                        v-if="isAdmin"
                        to="/admin/users"
                        class="block rounded-lg px-2 py-2 text-body transition-colors hover:bg-raised hover:text-strong"
                    >
                        Users
                    </RouterLink>
                    <Button
                        severity="secondary"
                        text
                        size="small"
                        class="w-full justify-start"
                        :label="isDark ? '☀ Light theme' : '☾ Dark theme'"
                        @click="toggleTheme"
                    />
                    <Button
                        severity="secondary"
                        text
                        size="small"
                        class="w-full justify-start"
                        label="Log out"
                        @click="logout"
                    />
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
        <CallLayer/>
    </div>
</template>
