<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import Sortable from 'sortablejs'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Popover from 'primevue/popover'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import * as boardApi from '../api/board'
import type { BoardCardPayload, CardPayload, ColumnKind, ColumnPayload, LabelPayload } from '../api/board'
import * as projectsApi from '../api/projects'
import type { ProjectDetail } from '../api/projects'
import { ApiError } from '../api/client'
import CallBadge from '../call/CallBadge.vue'
import CallButton from '../call/CallButton.vue'
import CardDialog from '../components/CardDialog.vue'
import CardPeople from '../components/CardPeople.vue'
import LabelsDialog from '../components/LabelsDialog.vue'
import ProjectUsersDialog from '../components/ProjectUsersDialog.vue'
import RenameColumnDialog from '../components/RenameColumnDialog.vue'
import StickerCard from '../components/StickerCard.vue'
import { useBoardRealtime, type RealtimeChange } from '../composables/useBoardRealtime'
import { usePresenceHeartbeat } from '../composables/usePresenceHeartbeat'
import { useAuthStore } from '../stores/auth'
import UserAvatar from "../components/UserAvatar.vue";

const CARD_EVENT_VERBS: Record<string, string> = {
    'card.created': 'created',
    'card.updated': 'updated',
    'card.moved': 'moved',
    'card.archived': 'archived',
    'card.restored': 'restored',
    'card.assigned': 'changed assignees on',
    'card.unassigned': 'changed assignees on',
    'card.executor_assigned': 'changed the executor of',
    'card.executor_unassigned': 'changed the executor of',
    'card.label_added': 'added a label to',
    'card.label_removed': 'removed a label from',
    'card.occurrence_completed': 'completed',
    'card.occurrence_uncompleted': 'reopened',
    'card.completed': 'completed',
    'card.uncompleted': 'reopened',
    'card.recurrence_changed': 'changed the recurrence of',
    'comment.created': 'commented on',
    'comment.updated': 'edited a comment on',
    'comment.deleted': 'deleted a comment on',
    'attachment.added': 'attached a file to',
    'attachment.removed': 'removed an attachment from',
}

const BOARD_EVENT_VERBS: Record<string, string> = {
    'column.created': 'created a column',
    'column.renamed': 'renamed a column',
    'column.deleted': 'deleted a column',
    'column.reordered': 'reordered columns',
    'label.created': 'created a label',
    'label.updated': 'updated a label',
    'label.deleted': 'deleted a label',
    'project.archived': 'archived the project',
    'project.restored': 'restored the project',
    'project.member_role_changed': 'changed a member role',
}

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()
const projectId = computed(() => route.params.id as string)

const detail = ref<ProjectDetail | null>(null)
const columns = ref<ColumnPayload[]>([])
const cards = ref<BoardCardPayload[]>([])
const labels = ref<LabelPayload[]>([])
const showArchived = ref(false)
const error = ref<string | null>(null)
const actionError = ref<string | null>(null)

const isAdmin = computed(() => detail.value?.project.role === 'admin')

const memberNames = computed(() => {
    const names = new Map<string, string>()
    for (const member of detail.value?.members ?? []) {
        names.set(member.userId, member.displayName)
    }
    return names
})

const memberAvatars = computed(() => {
    const versions = new Map<string, string | null>()
    for (const member of detail.value?.members ?? []) {
        versions.set(member.userId, member.avatarVersion)
    }
    return versions
})

const memberColors = computed(() => {
    const colors = new Map<string, string | null>()
    for (const member of detail.value?.members ?? []) {
        colors.set(member.userId, member.avatarColor)
    }
    return colors
})

const labelColors = computed(() => {
    const colors = new Map<string, string>()
    for (const label of labels.value) {
        colors.set(label.id, label.color)
    }
    return colors
})

const labelNames = computed(() => {
    const names = new Map<string, string>()
    for (const label of labels.value) {
        names.set(label.id, label.name)
    }
    return names
})

function labelColor(labelId: string): string | undefined {
    return labelColors.value.get(labelId)
}

function columnCards(columnId: string): BoardCardPayload[] {
    return cards.value
        .filter((card) => card.columnId === columnId)
        .sort((a, b) => a.position - b.position)
}

const unseenByColumn = computed(() => {
    const counts = new Map<string, number>()
    for (const card of cards.value) {
        if (card.unseenCount === 0) continue
        counts.set(card.columnId, (counts.get(card.columnId) ?? 0) + card.unseenCount)
    }
    return counts
})

function columnUnseen(columnId: string): number {
    return unseenByColumn.value.get(columnId) ?? 0
}

function unseenLabel(count: number): string {
    return count === 1 ? '1 new event' : `${count} new events`
}

const boardEl = ref<HTMLElement | null>(null)
let sortable: Sortable | null = null
const cardSortables: Sortable[] = []

const addingColumn = ref(false)
const newColumnName = ref('')
const newColumnKind = ref<ColumnKind>('task')

const COLUMN_KINDS: { label: string; value: ColumnKind }[] = [
    { label: 'Task', value: 'task' },
    { label: 'Sticker', value: 'sticker' },
]

const addingCardColumnId = ref<string | null>(null)
const newCardTitle = ref('')

const openColumnMenuId = ref<string | null>(null)
const cardMenuPopover = ref<InstanceType<typeof Popover> | null>(null)
const cardMenuCard = ref<BoardCardPayload | null>(null)
const renameTarget = ref<ColumnPayload | null>(null)
const deleting = ref(false)

const activeCard = ref<CardPayload | null>(null)
const showLabelsDialog = ref(false)
const showUsersDialog = ref(false)

const cardIdFromRoute = computed<string | null>(() => {
    const value = route.query.card
    return typeof value === 'string' && value !== '' ? value : null
})

const realtimeTick = ref(0)
let dragging = false

const { onlineUserIds, presenceAvailable, refreshPresence } = useBoardRealtime(projectId, refreshFromRealtime, () => !dragging)
usePresenceHeartbeat(projectId)

async function refreshFromRealtime(change: RealtimeChange): Promise<void> {
    realtimeTick.value++
    const openCard = activeCard.value
    if (openCard !== null) await markSeen(openCard.id)
    await load()
    notifyChange(change)
}

function notifyChange(change: RealtimeChange): void {
    if (change.event === 'reconnect') return
    if (change.actorId !== null && change.actorId === auth.user?.id) return

    const actor = (change.actorId !== null ? memberNames.value.get(change.actorId) : undefined) ?? 'Someone'
    const cardVerb = CARD_EVENT_VERBS[change.event]
    if (cardVerb !== undefined) {
        const card = cards.value.find((current) => current.id === change.cardId)
        const target = card !== undefined ? `“${card.title}”` : 'a card'
        toast.add({ severity: 'info', summary: `${actor} ${cardVerb} ${target}`, life: 4000 })
        return
    }

    const boardVerb = BOARD_EVENT_VERBS[change.event]
    if (boardVerb !== undefined) {
        toast.add({ severity: 'info', summary: `${actor} ${boardVerb}`, life: 4000 })
    }
}

function markSeen(cardId: string): Promise<void> {
    const card = cards.value.find((c) => c.id === cardId)
    if (card !== undefined) card.unseenCount = 0
    // Best-effort: a failed call only delays the indicator clearing until the next load.
    return boardApi.markCardSeen(cardId).catch(() => undefined)
}

function canToggleCompletion(card: BoardCardPayload): boolean {
    return card.executorId === null || card.executorId === auth.user?.id
}

function occurrenceTitle(card: BoardCardPayload): string {
    const occurrence = card.occurrence
    if (occurrence === null) return ''
    if (occurrence.state === 'pending') {
        return canToggleCompletion(card)
            ? `Mark completed for ${occurrence.period}`
            : `Waiting to be completed for ${occurrence.period}`
    }
    return canToggleCompletion(card) ? 'Mark uncompleted' : `Completed for ${occurrence.period}`
}

async function toggleOccurrence(card: BoardCardPayload): Promise<void> {
    const occurrence = card.occurrence
    if (occurrence === null) return
    const next = occurrence.state === 'pending' ? 'completed' : 'pending'
    card.occurrence = { state: next, period: occurrence.period }
    try {
        if (next === 'completed') {
            await boardApi.completeCard(card.id)
        } else {
            await boardApi.uncompleteCard(card.id)
        }
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Failed to update occurrence'
        card.occurrence = occurrence
        await load()
    }
}

async function toggleSticker(card: BoardCardPayload): Promise<void> {
    if (!canToggleCompletion(card)) return
    void markSeen(card.id)
    const previous = card.completedAt
    card.completedAt = previous === null ? new Date().toISOString() : null
    try {
        if (previous === null) {
            await boardApi.completeCard(card.id)
        } else {
            await boardApi.uncompleteCard(card.id)
        }
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Failed to update sticker'
        card.completedAt = previous
        await load()
    }
}

async function load(): Promise<void> {
    error.value = null
    try {
        const [projectDetail, board] = await Promise.all([
            projectsApi.getProject(projectId.value),
            boardApi.getBoard(projectId.value, showArchived.value),
        ])
        detail.value = projectDetail
        columns.value = board.columns
        cards.value = board.cards
        labels.value = board.labels
        await syncActiveCard()
        await nextTick()
        initCardSortables()
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to load board'
    }
}

async function syncActiveCard(): Promise<void> {
    const id = cardIdFromRoute.value
    if (id === null) {
        activeCard.value = null
        return
    }

    const card = cards.value.find((current) => current.id === id)
    if (card) {
        if (activeCard.value?.id !== card.id) void markSeen(card.id)
        activeCard.value = card
        return
    }

    try {
        const { card: fetchedCard } = await boardApi.getCard(id)
        if (cardIdFromRoute.value !== id) return
        if (activeCard.value?.id !== fetchedCard.id) void markSeen(fetchedCard.id)
        activeCard.value = fetchedCard
    } catch {
        if (cardIdFromRoute.value !== id) return
        activeCard.value = null
        await removeCardQuery()
    }
}

async function removeCardQuery(): Promise<void> {
    const query = { ...route.query }
    delete query.card
    delete query.tab
    await router.replace({ query })
}

function initSortable(): void {
    if (!boardEl.value) return
    sortable = Sortable.create(boardEl.value, {
        animation: 150,
        draggable: '.board-column',
        handle: '.column-drag-handle',
        delay: 250,
        delayOnTouchOnly: true,
        onStart: () => {
            dragging = true
        },
        onEnd: onDragEnd,
    })
}

async function onDragEnd(evt: Sortable.SortableEvent): Promise<void> {
    try {
        const { oldIndex, newIndex } = evt
        if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) return

        const moved = columns.value[oldIndex]
        columns.value.splice(oldIndex, 1)
        columns.value.splice(newIndex, 0, moved)
        columns.value.forEach((c, i) => (c.position = i))

        try {
            await boardApi.reorderColumn(moved.id, newIndex)
        } catch (e) {
            actionError.value = e instanceof ApiError ? e.message : 'Failed to reorder column'
            await load()
        }
    } finally {
        dragging = false
    }
}

function initCardSortables(): void {
    for (const instance of cardSortables) instance.destroy()
    cardSortables.length = 0
    if (!boardEl.value) return
    for (const listEl of boardEl.value.querySelectorAll<HTMLElement>('.card-list')) {
        const group = listEl.dataset.columnKind === 'sticker' ? 'board-stickers' : 'board-cards'
        cardSortables.push(
            Sortable.create(listEl, {
                group,
                animation: 150,
                ghostClass: 'opacity-40',
                draggable: '.board-card',
                filter: '.people-badge, .card-menu, .occurrence-pill, .sticker-check, .sticker-edit',
                preventOnFilter: false,
                delay: 250,
                delayOnTouchOnly: true,
                onStart: () => {
                    dragging = true
                },
                onEnd: onCardDragEnd,
            }),
        )
    }
}

async function onCardDragEnd(evt: Sortable.SortableEvent): Promise<void> {
    try {
        const { oldIndex, newIndex } = evt
        if (oldIndex === undefined || newIndex === undefined) return

        const fromEl = evt.from as HTMLElement
        const toEl = evt.to as HTMLElement
        const cardId = evt.item.dataset.cardId
        const sourceColumnId = fromEl.dataset.columnId
        const targetColumnId = toEl.dataset.columnId
        if (!cardId || !sourceColumnId || !targetColumnId) return
        if (sourceColumnId === targetColumnId && oldIndex === newIndex) return

        const card = cards.value.find((c) => c.id === cardId)
        if (!card) return

        // Sortable already moved the DOM node; mirror the move in state, then persist.
        const sourceCards = columnCards(sourceColumnId)
        const sourceIndex = sourceCards.findIndex((c) => c.id === cardId)
        if (sourceIndex === -1) return
        sourceCards.splice(sourceIndex, 1)
        sourceCards.forEach((c, i) => (c.position = i))

        if (sourceColumnId === targetColumnId) {
            sourceCards.splice(Math.min(newIndex, sourceCards.length), 0, card)
            sourceCards.forEach((c, i) => (c.position = i))
        } else {
            card.columnId = targetColumnId
            const targetCards = columnCards(targetColumnId)
            targetCards.splice(Math.min(newIndex, targetCards.length), 0, card)
            targetCards.forEach((c, i) => (c.position = i))
        }

        try {
            await boardApi.moveCard(cardId, targetColumnId, newIndex)
        } catch (e) {
            actionError.value = e instanceof ApiError ? e.message : 'Failed to move card'
            await load()
        }
    } finally {
        dragging = false
    }
}

async function moveCardToColumn(card: BoardCardPayload, targetColumnId: string): Promise<void> {
    const current = cards.value.find((c) => c.id === card.id)
    if (current === undefined || current.columnId === targetColumnId) return
    actionError.value = null

    const sourceCards = columnCards(current.columnId)
    const sourceIndex = sourceCards.findIndex((c) => c.id === current.id)
    if (sourceIndex === -1) return
    sourceCards.splice(sourceIndex, 1)
    sourceCards.forEach((c, i) => (c.position = i))

    current.columnId = targetColumnId
    const targetCards = columnCards(targetColumnId)
    targetCards.push(current)
    targetCards.forEach((c, i) => (c.position = i))

    try {
        await boardApi.moveCard(current.id, targetColumnId, targetCards.length - 1)
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Failed to move card'
        await load()
    }
}

const moveTargets = computed((): ColumnPayload[] => {
    const card = cardMenuCard.value
    if (card === null) return []
    const kind = columns.value.find((column) => column.id === card.columnId)?.kind
    return columns.value.filter((column) => column.kind === kind)
})

function toggleCardMenu(event: Event, card: BoardCardPayload): void {
    openColumnMenuId.value = null
    const popover = cardMenuPopover.value
    if (popover === null) return
    if (cardMenuCard.value?.id === card.id) {
        popover.hide()
        return
    }
    cardMenuCard.value = card
    popover.show(event)
    void nextTick(() => popover.alignOverlay())
}

function onCardMenuColumn(columnId: string | null): void {
    const card = cardMenuCard.value
    cardMenuPopover.value?.hide()
    if (card === null || columnId === null) return
    void moveCardToColumn(card, columnId)
}

function toggleColumnMenu(column: ColumnPayload): void {
    cardMenuPopover.value?.hide()
    openColumnMenuId.value = openColumnMenuId.value === column.id ? null : column.id
}

async function addColumn(): Promise<void> {
    const name = newColumnName.value.trim()
    if (name === '') return
    actionError.value = null
    try {
        const column = await boardApi.createColumn(projectId.value, name, newColumnKind.value)
        columns.value.push(column)
        newColumnName.value = ''
        newColumnKind.value = 'task'
        addingColumn.value = false
        await nextTick()
        initCardSortables()
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Failed to add column'
    }
}

function cancelAdd(): void {
    addingColumn.value = false
    newColumnName.value = ''
    newColumnKind.value = 'task'
}

function onRenamed(name: string): void {
    if (renameTarget.value) renameTarget.value.name = name
}

function askDelete(column: ColumnPayload): void {
    confirm.require({
        header: 'Delete column',
        message: `Delete column “${column.name}”? Columns that contain cards cannot be deleted.`,
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () => confirmDelete(column),
    })
}

async function confirmDelete(target: ColumnPayload): Promise<void> {
    if (deleting.value) return
    deleting.value = true
    actionError.value = null
    try {
        await boardApi.deleteColumn(target.id)
        columns.value = columns.value.filter((c) => c.id !== target.id)
        columns.value.forEach((c, i) => (c.position = i))
        await nextTick()
        initCardSortables()
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Failed to delete column'
    } finally {
        deleting.value = false
    }
}

function startAddCard(columnId: string): void {
    addingCardColumnId.value = columnId
    newCardTitle.value = ''
}

function cancelAddCard(): void {
    addingCardColumnId.value = null
    newCardTitle.value = ''
}

async function addCard(columnId: string): Promise<void> {
    const title = newCardTitle.value.trim()
    if (title === '') return
    actionError.value = null
    try {
        const card = await boardApi.createCard(columnId, title)
        cards.value.push({ ...card, unseenCount: 0, occurrence: null })
        cancelAddCard()
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Failed to add card'
    }
}

function onCardUpdated(patch: Partial<CardPayload>): void {
    const card = activeCard.value
    if (!card) return
    Object.assign(card, patch)
    if (patch.archived && !showArchived.value) {
        cards.value = cards.value.filter((c) => c.id !== card.id)
        closeCard()
    }
}

async function toggleArchived(): Promise<void> {
    showArchived.value = !showArchived.value
    await load()
}

async function onLabelsChanged(): Promise<void> {
    await load()
}

function onDocumentClick(): void {
    openColumnMenuId.value = null
}

onMounted(async () => {
    document.addEventListener('click', onDocumentClick)
    await load()
    await nextTick()
    initSortable()
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    sortable?.destroy()
    for (const instance of cardSortables) instance.destroy()
    cardSortables.length = 0
})

watch(projectId, async () => {
    openColumnMenuId.value = null
    cardMenuPopover.value?.hide()
    cardMenuCard.value = null
    addingColumn.value = false
    newColumnName.value = ''
    newColumnKind.value = 'task'
    addingCardColumnId.value = null
    newCardTitle.value = ''
    showArchived.value = false
    activeCard.value = null
    showLabelsDialog.value = false
    if (cardIdFromRoute.value !== null) {
        await removeCardQuery()
    }
    await load()
})

watch(
    () => route.query.card,
    () => {
        void syncActiveCard()
    },
)

watch(
    () => route.query.tab,
    (value) => {
        if (value == null) return
        const query = { ...route.query }
        delete query.tab
        void router.replace({ query })
    },
    { immediate: true },
)

function setActiveCard(card: BoardCardPayload): void {
    activeCard.value = card
    void markSeen(card.id)
    if (cardIdFromRoute.value === card.id) return
    const query: LocationQueryRaw = { ...route.query, card: card.id }
    delete query.tab
    void router.push({ query })
}

function closeCard(): void {
    activeCard.value = null
    void removeCardQuery()
}

function openUsersDialog(): void {
    showUsersDialog.value = true
    void refreshPresence()
}
</script>

<template>
    <main class="flex min-w-0 flex-1 flex-col">
        <div class="border-b border-line px-4 py-3 sm:px-6 sm:py-4">
            <div
                v-if="error"
                class="rounded-lg border border-danger/40 bg-danger/10 px-4 py-3 text-danger-text">
                {{ error }}
            </div>
            <template v-else-if="detail">
                <div class="mb-2 flex flex-wrap items-center gap-4 md:gap-10">
                    <h1 class="text-xl font-semibold sm:text-2xl">{{ detail.project.name }}</h1>
                    <span
                        v-if="detail.project.archived"
                        class="rounded-full border border-warn/40 bg-warn/10 px-2 py-0.5 text-xs text-warn-text">
                        Archived</span>
                    <div>
                        <CallButton v-if="!detail.project.archived" :project-id="projectId"/>
                        <Button
                            severity="info" text size="small" class="fa-lg"
                            title="Users" aria-label="Users"
                            @click="openUsersDialog">
                            <i class="fa-solid fa-user-group"></i>
                        </Button>
                        <Button
                            severity="secondary" text size="small" class="fa-lg"
                            title="Labels" aria-label="Labels"
                            @click="showLabelsDialog = true">
                            <i class="fa-solid fa-tags"></i>
                        </Button>
                        <Button
                            :severity="showArchived ? 'info' : 'secondary'"
                            text size="small" class="fa-lg"
                            :title="showArchived ? 'Hide archived cards' : 'Show archived cards'"
                            :aria-label="showArchived ? 'Hide archived cards' : 'Show archived cards'"
                            :aria-pressed="showArchived"
                            @click="toggleArchived">
                            <i class="fa-regular fa-trash-can"></i>
                        </Button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="member in detail.members"
                        :key="member.userId"
                        class="rounded-full bg-raised px-2.5 py-1 text-xs text-body">
                        {{ member.displayName }}
                        <span v-if="member.role === 'admin'" class="text-muted">· admin</span>
                        <CallBadge :project-id="projectId" :user-id="member.userId"/>
                    </span>
                </div>
            </template>
            <p v-else class="text-faint">Loading…</p>
        </div>

        <div class="flex min-h-0 flex-1 flex-col p-3 sm:p-6">
            <p v-if="actionError"
               class="mb-3 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                {{ actionError }}
            </p>
            <div
                v-if="detail"
                ref="boardEl"
                class="flex min-h-0 flex-1 items-start gap-3 overflow-x-auto pb-2 max-sm:snap-x max-sm:snap-mandatory">
                <p v-if="columns.length === 0" class="max-w-xs py-2 text-faint">
                    This board has no columns yet. Use “+ Add column” to start organizing cards.
                </p>
                <div
                    v-for="column in columns" :key="column.id"
                    class="board-column relative flex max-h-full w-full shrink-0 snap-start flex-col rounded-xl bg-panel sm:w-72">
                    <div class="flex items-center border-b border-line">
                        <div class="column-drag-handle flex min-w-0 flex-1 items-center gap-2 cursor-grab px-3 py-2 active:cursor-grabbing">
                            <i v-if="column.kind === 'sticker'" class="fa-solid fa-note-sticky shrink-0 text-xs text-muted" title="Sticker column" aria-hidden="true"/>
                            <span class="min-w-0 truncate font-semibold">{{ column.name }}</span>
                            <span
                                v-if="columnUnseen(column.id) > 0"
                                class="shrink-0 rounded-full bg-brand/15 px-1.5 py-0.5 text-xs font-medium text-brand-text"
                                :aria-label="unseenLabel(columnUnseen(column.id))">{{ columnUnseen(column.id) }}</span>
                        </div>
                        <Button
                            label="⋯" aria-label="Column actions"
                            severity="secondary" size="small" text
                            @click.stop="toggleColumnMenu(column)"/>
                        <div
                            v-if="openColumnMenuId === column.id"
                            class="absolute right-2 top-10 z-10 w-32 rounded-lg border border-line-strong bg-raised py-1 shadow-xl">
                            <Button
                                severity="secondary" text size="small" class="w-full justify-start" label="Rename"
                                @click="renameTarget = column"/>
                            <Button
                                v-if="isAdmin"
                                severity="danger" text size="small" class="w-full justify-start" label="Delete"
                                @click="askDelete(column)"/>
                        </div>
                    </div>
                    <div class="flex min-h-0 flex-col gap-1 p-2">
                        <div
                            :data-column-id="column.id"
                            :data-column-kind="column.kind"
                            class="card-list -mx-1 flex min-h-10 flex-col gap-1.5 overflow-y-auto px-1 pb-1">
                            <template v-if="column.kind === 'sticker'">
                                <StickerCard
                                    v-for="card in columnCards(column.id)"
                                    :key="card.id"
                                    :card="card"
                                    :can-toggle="canToggleCompletion(card)"
                                    @toggle="toggleSticker(card)"
                                    @open="setActiveCard(card)"/>
                            </template>
                            <template v-else>
                                <Card
                                    v-for="card in columnCards(column.id)"
                                    :key="card.id"
                                    class="board-card w-full shrink-0 cursor-pointer rounded-lg border border-line bg-raised shadow-card transition-colors hover:border-line-emphasis"
                                    :class="{ 'opacity-50': card.archived }"
                                    :data-card-id="card.id"
                                    @click="setActiveCard(card)">
                                    <template #content>
                                        <div v-if="card.labelIds.length > 0" class="mb-1.5 flex flex-wrap gap-1">
                                        <span
                                            v-for="labelId in card.labelIds" :key="labelId"
                                            class="h-2 w-6 rounded-sm"
                                            :title="labelNames.get(labelId)"
                                            :style="{ backgroundColor: labelColor(labelId) ?? '#52525b' }"/>
                                        </div>
                                        <div class="flex items-start gap-2">
                                            <button type="button"
                                                    class="min-w-0 flex-1 cursor-pointer rounded-sm text-left break-words focus-visible:outline-2 focus-visible:outline-brand"
                                                    :class="{ 'line-through text-muted': card.occurrence?.state === 'completed' }">
                                                {{ card.title }}
                                            </button>
                                            <span
                                                v-if="card.unseenCount > 0"
                                                class="mt-0.5 shrink-0 rounded-full bg-brand/15 px-1.5 py-0.5 text-xs font-semibold text-brand-text"
                                                :title="unseenLabel(card.unseenCount)"
                                                :aria-label="unseenLabel(card.unseenCount)">{{ card.unseenCount }}</span>
                                            <template v-if="card.occurrence !== null">
                                                <button
                                                    v-if="canToggleCompletion(card)"
                                                    type="button"
                                                    class="occurrence-pill mt-0.5 shrink-0 cursor-pointer rounded-full px-1.5 py-0.5 text-sm font-semibold"
                                                    :class="card.occurrence.state === 'pending' ? 'bg-brand/15 text-brand-text' : 'bg-active text-muted'"
                                                    :title="occurrenceTitle(card)"
                                                    :aria-label="occurrenceTitle(card)"
                                                    @click.stop="toggleOccurrence(card)">
                                                    <i :class="card.occurrence.state === 'pending' ? 'fa-regular fa-circle-check' : 'fa-solid fa-circle-check'" aria-hidden="true"/>
                                                </button>
                                                <span
                                                    v-else
                                                    class="occurrence-pill mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-sm font-semibold"
                                                    :class="card.occurrence.state === 'pending' ? 'bg-brand/15 text-brand-text' : 'bg-active text-muted'"
                                                    :title="occurrenceTitle(card)"
                                                    :aria-label="occurrenceTitle(card)">==
                                                    <i :class="card.occurrence.state === 'pending' ? 'fa-regular fa-circle-check' : 'fa-solid fa-circle-check'" aria-hidden="true"/>
                                                </span>
                                            </template>
                                            <Button
                                                class="card-menu -my-1 -mr-1 shrink-0"
                                                label="⋯" aria-label="Card actions"
                                                severity="secondary" size="small" text
                                                @click.stop="toggleCardMenu($event, card)"/>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                                            <span v-if="card.dueDate">Due {{ card.dueDate }}</span>
                                            <span v-if="card.archived" class="rounded bg-active px-1.5 py-0.5 text-body">
                                                Archived
                                            </span>
                                            <CardPeople
                                                :card-title="card.title"
                                                :creator-id="card.creatorId"
                                                :executor-id="card.executorId"
                                                :assignee-ids="card.assigneeIds"
                                                :member-names="memberNames"
                                                :member-avatars="memberAvatars"
                                                :member-colors="memberColors"/>
                                        </div>
                                    </template>
                                </Card>
                            </template>
                            <p v-if="columnCards(column.id).length === 0" class="px-2 py-1 text-xs text-faint">
                                {{ column.kind === 'sticker' ? 'No stickers yet' : 'No cards yet' }}
                            </p>
                        </div>

                        <div v-if="addingCardColumnId === column.id" class="mt-1">
                            <InputText
                                v-model="newCardTitle"
                                class="w-full" autofocus
                                :placeholder="column.kind === 'sticker' ? 'Sticker text' : 'Card title'"
                                @keydown.enter="addCard(column.id)" @keydown.esc="cancelAddCard"/>
                            <div class="mt-1.5 flex gap-2">
                                <Button label="Add" size="small" :disabled="newCardTitle.trim() === ''" @click="addCard(column.id)"/>
                                <Button severity="secondary" size="small" label="Cancel" @click="cancelAddCard"/>
                            </div>
                        </div>
                        <Button
                            v-else @click="startAddCard(column.id)"
                            severity="secondary" text size="small"
                            :label="column.kind === 'sticker' ? '+ Add sticker' : '+ Add card'"
                            class="mt-1 w-full justify-start text-body hover:bg-raised"/>
                    </div>
                </div>

                <div v-if="addingColumn" class="w-full shrink-0 snap-start rounded-xl border border-line-strong bg-panel p-2 sm:w-72">
                    <InputText
                        v-model="newColumnName"
                        autofocus placeholder="Column name" class="w-full"
                        @keydown.enter="addColumn" @keydown.esc="cancelAdd"/>
                    <div class="mt-2 flex gap-1">
                        <button
                            v-for="option in COLUMN_KINDS" :key="option.value"
                            type="button"
                            class="cursor-pointer rounded-full border px-2.5 py-1 text-xs transition-colors"
                            :class="newColumnKind === option.value ? 'border-brand bg-brand/15 font-medium text-brand-text' : 'border-line-strong text-muted hover:border-line-emphasis hover:text-body'"
                            @click="newColumnKind = option.value">{{ option.label }}
                        </button>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <Button label="Add" :disabled="newColumnName.trim() === ''" @click="addColumn"/>
                        <Button severity="secondary" label="Cancel" @click="cancelAdd"/>
                    </div>
                </div>
                <button
                    v-else
                    @click="addingColumn = true" type="button"
                    class="w-full shrink-0 cursor-pointer snap-start rounded-xl bg-panel/50 px-3 py-2 text-left text-body transition-colors hover:bg-panel hover:text-strong sm:mr-6 sm:w-72">
                    + Add column
                </button>
            </div>
        </div>

        <Popover ref="cardMenuPopover" class="w-56" :pt="{ content: 'p-2' }">
            <div v-if="cardMenuCard">
                <p class="mb-1.5 text-xs font-medium text-muted">Move to column</p>
                <Select
                    :model-value="cardMenuCard.columnId"
                    :options="moveTargets"
                    option-label="name"
                    option-value="id"
                    size="small"
                    fluid
                    @change="onCardMenuColumn($event.value)"/>
            </div>
        </Popover>

        <RenameColumnDialog
            v-if="renameTarget"
            :column-id="renameTarget.id"
            :initial-name="renameTarget.name"
            @close="renameTarget = null"
            @renamed="onRenamed"
        />
        <CardDialog
            v-if="activeCard"
            :key="activeCard.id"
            :card="activeCard"
            :labels="labels"
            :members="detail?.members ?? []"
            :columns="columns"
            :is-admin="isAdmin"
            :realtime-tick="realtimeTick"
            @close="closeCard"
            @updated="onCardUpdated"
        />
        <LabelsDialog
            v-if="showLabelsDialog"
            :project-id="projectId"
            :labels="labels"
            :can-delete="isAdmin"
            @close="showLabelsDialog = false"
            @changed="onLabelsChanged"
        />
        <ProjectUsersDialog
            v-if="showUsersDialog"
            :project-id="projectId"
            :online-user-ids="onlineUserIds"
            :presence-available="presenceAvailable"
            @close="showUsersDialog = false"
        />
    </main>
</template>
