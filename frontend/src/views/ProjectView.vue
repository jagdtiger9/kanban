<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Sortable from 'sortablejs'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import { useConfirm } from 'primevue/useconfirm'
import * as boardApi from '../api/board'
import type { CardPayload, ColumnPayload, LabelPayload } from '../api/board'
import * as projectsApi from '../api/projects'
import type { ProjectDetail } from '../api/projects'
import { ApiError } from '../api/client'
import CardDialog from '../components/CardDialog.vue'
import CardPeople from '../components/CardPeople.vue'
import LabelsDialog from '../components/LabelsDialog.vue'
import RenameColumnDialog from '../components/RenameColumnDialog.vue'
import { useBoardRealtime } from '../composables/useBoardRealtime'

const route = useRoute()
const confirm = useConfirm()
const projectId = computed(() => route.params.id as string)

const detail = ref<ProjectDetail | null>(null)
const columns = ref<ColumnPayload[]>([])
const cards = ref<CardPayload[]>([])
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

const labelColors = computed(() => {
    const colors = new Map<string, string>()
    for (const label of labels.value) {
        colors.set(label.id, label.color)
    }
    return colors
})

function labelColor(labelId: string): string | undefined {
    return labelColors.value.get(labelId)
}

function columnCards(columnId: string): CardPayload[] {
    return cards.value
        .filter((card) => card.columnId === columnId)
        .sort((a, b) => a.position - b.position)
}

const boardEl = ref<HTMLElement | null>(null)
let sortable: Sortable | null = null
const cardSortables: Sortable[] = []

const addingColumn = ref(false)
const newColumnName = ref('')

const addingCardColumnId = ref<string | null>(null)
const newCardTitle = ref('')

const openMenuId = ref<string | null>(null)
const renameTarget = ref<ColumnPayload | null>(null)
const deleting = ref(false)

const activeCard = ref<CardPayload | null>(null)
const showLabelsDialog = ref(false)

const realtimeTick = ref(0)
let dragging = false

useBoardRealtime(projectId, refreshFromRealtime, () => !dragging)

async function refreshFromRealtime(): Promise<void> {
    realtimeTick.value++
    await load()
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
        if (activeCard.value) {
            activeCard.value = cards.value.find((card) => card.id === activeCard.value?.id) ?? null
        }
        await nextTick()
        initCardSortables()
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to load board'
    }
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
        cardSortables.push(
            Sortable.create(listEl, {
                group: 'board-cards',
                animation: 150,
                ghostClass: 'opacity-40',
                draggable: '.board-card',
                filter: '.people-badge',
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

async function addColumn(): Promise<void> {
    const name = newColumnName.value.trim()
    if (name === '') return
    actionError.value = null
    try {
        const column = await boardApi.createColumn(projectId.value, name)
        columns.value.push(column)
        newColumnName.value = ''
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
        cards.value.push(card)
        cancelAddCard()
    } catch (e) {
        actionError.value = e instanceof ApiError ? e.message : 'Failed to add card'
    }
}

function onCardUpdated(patch: Partial<CardPayload>): void {
    const card = cards.value.find((c) => c.id === activeCard.value?.id)
    if (!card) return
    Object.assign(card, patch)
    if (patch.archived && !showArchived.value) {
        cards.value = cards.value.filter((c) => c.id !== card.id)
    }
}

async function toggleArchived(): Promise<void> {
    await load()
}

async function onLabelsChanged(): Promise<void> {
    await load()
}

function onDocumentClick(): void {
    openMenuId.value = null
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

watch(projectId, () => {
    openMenuId.value = null
    addingColumn.value = false
    newColumnName.value = ''
    addingCardColumnId.value = null
    newCardTitle.value = ''
    showArchived.value = false
    activeCard.value = null
    showLabelsDialog.value = false
    void load()
})
</script>

<template>
    <main class="flex min-w-0 flex-1 flex-col">
        <div class="border-b border-line px-4 py-3 sm:px-6 sm:py-4">
            <div
                v-if="error"
                class="rounded-lg border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger-text"
            >
                {{ error }}
            </div>
            <template v-else-if="detail">
                <div class="mb-2 flex items-center gap-3">
                    <h1 class="text-xl font-semibold text-strong sm:text-2xl">{{ detail.project.name }}</h1>
                    <span
                        v-if="detail.project.archived"
                        class="rounded-full border border-warn/40 bg-warn/10 px-2 py-0.5 text-xs text-warn-text"
                    >
            Archived
          </span>
                </div>
                <div class="flex flex-wrap gap-2">
          <span
              v-for="member in detail.members"
              :key="member.userId"
              class="rounded-full bg-raised px-2.5 py-1 text-xs text-body"
          >
            {{ member.displayName }}
            <span v-if="member.role === 'admin'" class="text-brand-text">· admin</span>
          </span>
                </div>
            </template>
            <p v-else class="text-sm text-faint">Loading…</p>
        </div>

        <div class="min-h-0 flex-1 overflow-auto p-3 sm:p-6">
            <p
                v-if="actionError"
                class="mb-3 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text"
            >
                {{ actionError }}
            </p>
            <div class="mb-3 flex items-center justify-between gap-3">
                <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-muted">
                    <Checkbox v-model="showArchived" :binary="true" @update:model-value="toggleArchived" />
                    Show archived cards
                </label>
                <Button severity="secondary" size="small" label="Labels" @click="showLabelsDialog = true"/>
            </div>
            <div ref="boardEl" class="flex items-start gap-3">
                <p v-if="detail && columns.length === 0" class="max-w-xs py-2 text-sm text-faint">
                    This board has no columns yet. Use “+ Add column” to start organizing cards.
                </p>
                <div
                    v-for="column in columns"
                    :key="column.id"
                    class="board-column relative w-[calc(100vw-3rem)] max-w-72 shrink-0 rounded-xl border border-line bg-panel/60 sm:w-72"
                >
                    <div class="flex items-center border-b border-line">
                        <div class="column-drag-handle min-w-0 flex-1 cursor-grab px-3 py-2 active:cursor-grabbing">
                            <span class="block truncate text-sm font-semibold text-strong">{{ column.name }}</span>
                        </div>
                        <Button
                            aria-label="Column actions"
                            severity="secondary"
                            text
                            size="small"
                            label="⋯"
                            @click.stop="openMenuId = openMenuId === column.id ? null : column.id"
                        />
                        <div
                            v-if="openMenuId === column.id"
                            class="absolute right-2 top-10 z-10 w-32 rounded-lg border border-line-strong bg-raised py-1 shadow-xl"
                        >
                            <Button
                                severity="secondary"
                                text
                                size="small"
                                class="w-full justify-start"
                                label="Rename"
                                @click="renameTarget = column"
                            />
                            <Button
                                v-if="isAdmin"
                                severity="danger"
                                text
                                size="small"
                                class="w-full justify-start"
                                label="Delete"
                                @click="askDelete(column)"
                            />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1 p-2">
                        <div :data-column-id="column.id" class="card-list flex min-h-10 flex-col gap-1.5">
                            <Card
                                v-for="card in columnCards(column.id)"
                                :key="card.id"
                                class="board-card block w-full cursor-pointer rounded-lg border border-line bg-raised/70 transition-colors hover:border-line-emphasis"
                                :class="{ 'opacity-50': card.archived }"
                                :data-card-id="card.id"
                                @click="activeCard = card"
                            >
                                <template #content>
                                    <div v-if="card.labelIds.length > 0" class="mb-1.5 flex flex-wrap gap-1">
                                        <span
                                            v-for="labelId in card.labelIds"
                                            :key="labelId"
                                            class="h-2 w-6 rounded-sm"
                                            :style="{ backgroundColor: labelColor(labelId) ?? '#52525b' }"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        class="block w-full cursor-pointer rounded-sm text-left text-sm break-words text-strong focus-visible:outline-2 focus-visible:outline-brand"
                                    >
                                        {{ card.title }}
                                    </button>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                                        <span v-if="card.dueDate">Due {{ card.dueDate }}</span>
                                        <span
                                            v-if="card.archived"
                                            class="rounded bg-active px-1.5 py-0.5 text-body"
                                        >
                                            Archived
                                        </span>
                                        <CardPeople
                                            :card-title="card.title"
                                            :creator-id="card.creatorId"
                                            :assignee-ids="card.assigneeIds"
                                            :member-names="memberNames"
                                        />
                                    </div>
                                </template>
                            </Card>
                            <p
                                v-if="columnCards(column.id).length === 0"
                                class="px-2 py-1 text-xs text-faint"
                            >
                                No cards yet
                            </p>
                        </div>

                        <div v-if="addingCardColumnId === column.id" class="mt-1">
                            <InputText
                                v-model="newCardTitle"
                                class="w-full"
                                autofocus
                                placeholder="Card title"
                                @keydown.enter="addCard(column.id)"
                                @keydown.esc="cancelAddCard"
                            />
                            <div class="mt-1.5 flex gap-2">
                                <Button label="Add" :disabled="newCardTitle.trim() === ''" @click="addCard(column.id)"/>
                                <Button severity="secondary" size="small" label="Cancel" @click="cancelAddCard"/>
                            </div>
                        </div>
                        <Button
                            v-else
                            severity="secondary"
                            text
                            size="small"
                            class="mt-1 w-full justify-start"
                            label="+ Add card"
                            @click="startAddCard(column.id)"
                        />
                    </div>
                </div>

                <div v-if="addingColumn" class="w-[calc(100vw-3rem)] max-w-72 shrink-0 rounded-xl border border-line-strong bg-panel p-2 sm:w-72">
                    <InputText
                        v-model="newColumnName"
                        autofocus
                        placeholder="Column name"
                        class="w-full"
                        @keydown.enter="addColumn"
                        @keydown.esc="cancelAdd"
                    />
                    <div class="mt-2 flex gap-2">
                        <Button label="Add" :disabled="newColumnName.trim() === ''" @click="addColumn"/>
                        <Button severity="secondary" label="Cancel" @click="cancelAdd"/>
                    </div>
                </div>
                <button
                    v-else
                    type="button"
                    class="w-[calc(100vw-3rem)] max-w-72 shrink-0 cursor-pointer rounded-xl border border-dashed border-line-strong px-3 py-2 text-left text-sm text-faint transition-colors hover:border-line-emphasis hover:text-body sm:w-72"
                    @click="addingColumn = true"
                >
                    + Add column
                </button>
            </div>
        </div>

        <RenameColumnDialog
            v-if="renameTarget"
            :column-id="renameTarget.id"
            :initial-name="renameTarget.name"
            @close="renameTarget = null"
            @renamed="onRenamed"
        />
        <CardDialog
            v-if="activeCard"
            :card="activeCard"
            :labels="labels"
            :members="detail?.members ?? []"
            :columns="columns"
            :is-admin="isAdmin"
            :realtime-tick="realtimeTick"
            @close="activeCard = null"
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
    </main>
</template>
