<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Divider, OverlayBadge, Popover } from 'primevue'
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import { useConfirm } from 'primevue/useconfirm'
import { ApiError, ping } from '../api/client'
import type { ProjectPayload } from '../api/projects'
import type { UserEventItem } from '../api/userEvents'
import MembersDialog from '../components/MembersDialog.vue'
import CallLayer from '../call/CallLayer.vue'
import ProjectCreateDialog from '../components/ProjectCreateDialog.vue'
import ProjectListItem from '../components/ProjectListItem.vue'
import RenameProjectDialog from '../components/RenameProjectDialog.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { useAuthStore } from '../stores/auth'
import { useProjectsStore } from '../stores/projects'
import { useUserEventsStore } from '../stores/userEvents'
import { useUserRealtime } from '../composables/useUserRealtime'
import { useTheme } from '../composables/useTheme'
import { relativeTime } from '../utils/dates'
import { isGlobalAdmin } from '../utils/roles'

type ApiState = 'checking' | 'ok' | 'down'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const projects = useProjectsStore()
const events = useUserEventsStore()
const confirm = useConfirm()
const { isDark, toggleTheme } = useTheme()

const notificationsOpen = ref(false)

useUserRealtime(computed(() => auth.user?.id ?? null), () => notificationsOpen.value)

watch(
    () => events.unreadCount,
    (count) => {
        document.title = count > 0 ? `(${count}) Kanban` : 'Kanban'
    },
    { immediate: true },
)

watch(notificationsOpen, async (open) => {
    if (!open) return
    if (!events.initialized) await events.load()
    await events.markSeen()
})

const USER_EVENT_VERBS: Record<string, (item: UserEventItem) => string> = {
    'card.created': (item) => `created “${item.cardTitle ?? 'a card'}”`,
    'card.assigned': (item) => `assigned you to “${item.cardTitle ?? 'a card'}”`,
    'card.unassigned': (item) => `unassigned you from “${item.cardTitle ?? 'a card'}”`,
    'comment.created': (item) => `commented on “${item.cardTitle ?? 'a card'}”`,
    'project.member_role_changed': (item) =>
        item.role !== null
            ? `changed your role in ${item.projectName ?? 'a project'} to ${item.role}`
            : `changed your role in ${item.projectName ?? 'a project'}`,
    'project.archived': (item) => `archived ${item.projectName ?? 'a project'}`,
    'project.restored': (item) => `restored ${item.projectName ?? 'a project'}`,
}

function verb(item: UserEventItem): string {
    const format = USER_EVENT_VERBS[item.type]
    return format !== undefined ? format(item) : 'sent you an update'
}

function openNotifications(): void {
    notificationsOpen.value = true
}

function openEvent(item: UserEventItem): void {
    if (item.projectId === null) return
    notificationsOpen.value = false
    void router.push({
        name: 'project',
        params: { id: item.projectId },
        query: item.cardId !== null ? { card: item.cardId } : {},
    })
}

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
    await projects.fetchProjects()
    void events.load()
    try {
        await ping()
        apiState.value = 'ok'
    } catch {
        apiState.value = 'down'
    }
})

onBeforeUnmount(() => {
    document.title = 'Kanban'
    document.removeEventListener('keydown', onKeydown)
})

async function logout(): Promise<void> {
    events.reset()
    await auth.logout()
    await router.push({ name: 'login' })
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

const settingsRef = ref()
const openSettings = (event: Event) => {
    settingsRef.value.toggle(event)
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
            <div class="lg:hidden">
                <button
                    type="button"
                    class="cursor-pointer text-white/70 hover:text-white"
                    :aria-label="events.unreadCount > 0 ? `Notifications (${events.unreadCount} unread)` : 'Notifications'"
                    title="Notifications"
                    @click="openNotifications">
                    <OverlayBadge
                        v-if="events.unreadCount"
                        :value="events.unreadCount > 9 ? '9+' : events.unreadCount"
                        size="small" severity="warn">
                        <i class="fa-regular fa-bell fa-lg"></i>
                    </OverlayBadge>
                    <i v-else class="fa-regular fa-bell fa-lg"></i>
                </button>
            </div>
            <div class="hidden lg:flex items-center gap-3">
                <div class="flex items-center gap-1.5 text-xs text-muted">
                    <span
                        class="size-2 rounded-full"
                        :class="apiState === 'ok' ? 'bg-emerald-500' : apiState === 'down' ? 'bg-danger' : 'bg-warn'"
                        :title="apiState === 'ok' ? 'api ok' : apiState === 'down' ? 'api down' : 'checking api…'"/>
                </div>
                <button
                    type="button"
                    class="cursor-pointer text-white/70 hover:text-white"
                    :aria-label="events.unreadCount > 0 ? `Notifications (${events.unreadCount} unread)` : 'Notifications'"
                    title="Notifications"
                    @click="openNotifications">
                    <OverlayBadge
                        v-if="events.unreadCount"
                        :value="events.unreadCount > 9 ? '9+' : events.unreadCount"
                        size="small" severity="warn">
                        <i class="fa-regular fa-bell fa-lg text-body"></i>
                    </OverlayBadge>
                    <i v-else class="fa-regular fa-bell fa-lg text-body"></i>
                </button>
                <button
                    type="button"
                    class="cursor-pointer text-body hover:text-strong"
                    :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    :title="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    @click="toggleTheme">
                    <i class="fa-solid fa-lg" :class="isDark ? 'fa-sun' : 'fa-moon'"></i>
                </button>
                <RouterLink
                    v-if="isAdmin"
                    to="/admin/users"
                    class="rounded-lg border border-line-strong px-3 py-1.5 text-body transition-colors hover:border-line-emphasis hover:text-strong">
                    Users
                </RouterLink>
                <div @click="openSettings($event)" class="cursor-pointer">
                    <UserAvatar
                        :user-id="auth.user?.id ?? ''"
                        :name="auth.user?.displayName ?? null"
                        :avatar-version="auth.user?.avatarVersion ?? null"
                        :color="auth.user?.avatarColor ?? null" size="md"/>
                </div>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <div v-if="drawerOpen" @click="drawerOpen = false"
                 class="fixed inset-0 z-30 bg-black/60 lg:hidden"/>
            <aside
                id="app-drawer"
                class="fixed inset-y-0 left-0 z-40 flex w-72 max-w-[calc(100vw-3rem)] shrink-0 flex-col overflow-y-auto border-r border-line bg-panel p-4 transition-transform duration-200 lg:static lg:z-auto lg:w-60 lg:max-w-none lg:translate-x-0 lg:bg-panel/40"
                :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'">
                <div class="mb-4 flex items-center justify-between lg:hidden">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-faint">Menu</h2>
                    <Button
                        @click="drawerOpen = false"
                        aria-label="Close menu" severity="secondary" text size="small" label="✕"/>
                </div>

                <div class="mb-4 flex items-center gap-3 border-b border-line pb-3 lg:hidden">
                    <UserAvatar
                        :user-id="auth.user?.id ?? ''"
                        :name="auth.user?.displayName ?? null"
                        :avatar-version="auth.user?.avatarVersion ?? null"
                        :color="auth.user?.avatarColor ?? null"
                        size="md"/>
                    <div class="truncate font-medium">{{ auth.user?.displayName }}</div>
                    <div
                        class="size-2 rounded-full"
                        :class="apiState === 'ok' ? 'bg-emerald-500' : apiState === 'down' ? 'bg-danger' : 'bg-warn'"
                        :title="apiState === 'ok' ? 'api ok' : apiState === 'down' ? 'api down' : 'checking api…'"/>
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
                        @delete="askDelete(project)"/>
                </ul>

                <div class="mb-3 flex items-center justify-between">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-1.5 text-sm font-semibold text-muted transition-colors hover:text-body"
                        :aria-expanded="!projectsCollapsed"
                        aria-controls="other-projects"
                        @click="projectsCollapsed = !projectsCollapsed">
                        <span class="w-4 text-base leading-none">{{ projectsCollapsed ? '▸' : '▾' }}</span>
                        Projects
                    </button>
                    <Button
                        aria-label="New project"
                        severity="secondary"
                        text
                        size="small"
                        label="+ New"
                        @click="showCreate = true"/>
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
                        class="block rounded-lg px-2 py-2 text-body transition-colors hover:bg-raised hover:text-strong">
                        Settings
                    </RouterLink>
                    <RouterLink
                        v-if="isAdmin"
                        to="/admin/users"
                        class="block rounded-lg px-2 py-2 text-body transition-colors hover:bg-raised hover:text-strong">
                        Users
                    </RouterLink>
                    <Button
                        severity="secondary"
                        text
                        size="small"
                        class="w-full justify-start"
                        :label="isDark ? 'Light theme' : 'Dark theme'"
                        :icon="isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon'"
                        @click="toggleTheme"/>
                    <Button
                        severity="secondary"
                        text
                        size="small"
                        class="w-full justify-start"
                        label="Log out"
                        @click="logout"/>
                </div>
            </aside>

            <RouterView/>
        </div>

        <ProjectCreateDialog v-if="showCreate" @close="showCreate = false" @created="onCreated"/>
        <RenameProjectDialog
            v-if="renameTarget"
            :project-id="renameTarget.id"
            :initial-name="renameTarget.name"
            @close="renameTarget = null"/>
        <MembersDialog v-if="membersTarget" :project-id="membersTarget.id" @close="membersTarget = null"/>
        <CallLayer/>

        <Drawer v-model:visible="notificationsOpen" position="right" header="Notifications" class="w-80 max-w-[90vw]">
            <p v-if="events.error" class="mb-3 rounded-lg border border-danger/40 bg-danger/10 px-2 py-1.5 text-xs text-danger-text">
                {{ events.error }}
            </p>
            <p v-else-if="!events.initialized" class="text-faint">Loading…</p>
            <p v-else-if="events.items.length === 0" class="text-faint">You're all caught up. New activity will appear here.</p>
            <ul v-else class="space-y-1">
                <li v-for="item in events.items" :key="item.id">
                    <button
                        type="button"
                        class="w-full cursor-pointer rounded-lg px-2 py-2 text-left transition-colors hover:bg-raised"
                        :class="item.seenAt === null ? 'bg-raised/60' : ''"
                        @click="openEvent(item)">
                        <span class="block text-sm leading-snug text-body">
                            <span class="font-medium text-strong">{{ item.actorName ?? 'Someone' }}</span>
                            {{ verb(item) }}
                        </span>
                        <span class="mt-0.5 block text-xs text-faint">
                            <template v-if="item.projectName !== null">{{ item.projectName }} · </template>
                            {{ relativeTime(item.occurredOn) }}
                        </span>
                    </button>
                </li>
            </ul>
            <div v-if="events.cursor !== null" class="mt-3">
                <Button
                    severity="secondary"
                    text
                    size="small"
                    class="w-full"
                    :loading="events.loading"
                    label="Load more"
                    @click="events.loadMore()"/>
            </div>
        </Drawer>

        <Popover ref="settingsRef">
            <div class="flex flex-col gap-3">
                <span class="text-body">{{ auth.user?.displayName }}</span>
                <Divider/>
                <RouterLink to="/settings">
                    <i class="fa-solid fa-gear fa-lg text-white/70 hover:text-white" title="Settings"></i>
                    Settings
                </RouterLink>
                <Button text @click="logout" class="p-1 justify-start">Log out</Button>
            </div>
        </Popover>
    </div>
</template>
