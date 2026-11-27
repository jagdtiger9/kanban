<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { Divider } from 'primevue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import * as boardApi from '../api/board'
import type {
    CardPayload,
    ChecklistItemPayload,
    ColumnPayload,
    LabelPayload,
    LinkedCardPayload,
    RecurrenceKind,
    RecurrenceRulePayload,
    TimelineItem,
} from '../api/board'
import type { AttachmentPayload } from '../api/attachments'
import * as attachmentsApi from '../api/attachments'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { parseCardRef } from '../utils/cardLink'
import { text2Clipboard } from '../utils/clipboard'
import { fromYmd, toYmd } from '../utils/dates'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'
import { MEDIA_ACCEPT } from '../utils/attachments'
import AttachmentChip from './AttachmentChip.vue'
import CardAssignees from './CardAssignees.vue'
import CardComments from './CardComments.vue'
import ClipboardPasteDialog from './ClipboardPasteDialog.vue'
import MarkdownEditorDialog from './MarkdownEditorDialog.vue'
import Modal from './Modal.vue'

const props = defineProps<{
    card: CardPayload
    labels: LabelPayload[]
    members: MemberPayload[]
    columns: ColumnPayload[]
    isAdmin: boolean
    realtimeTick?: number
}>()

const emit = defineEmits<{
    close: []
    updated: [patch: Partial<CardPayload>]
}>()

const auth = useAuthStore()
const confirm = useConfirm()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const isCreator = computed(() => auth.user?.id === props.card.creatorId)
const canManage = computed(() => isCreator.value || props.isAdmin)
const isSticker = computed(
    () => props.columns.find((column) => column.id === props.card.columnId)?.kind === 'sticker',
)

const title = ref(props.card.title)
const description = ref(props.card.description)
const labelIds = ref<string[]>([...props.card.labelIds])
const dueDate = ref<string | null>(props.card.dueDate)
const dueDateModel = computed(() => fromYmd(dueDate.value))
const recurrence = ref<RecurrenceRulePayload | null>(props.card.recurrence)
const scheduleMode = computed((): ScheduleMode => (recurrence.value === null ? 'single' : 'recurring'))

const checklist = ref<ChecklistItemPayload[]>([...props.card.checklist])
const checklistText = ref('')
const checklistBusy = ref(false)
const checklistAdding = ref(false)
const checklistInput = ref<ComponentPublicInstance | null>(null)
const pendingChecklistItems = new Set<string>()
const checklistDone = computed(() => checklist.value.filter((item) => item.done).length)

const error = ref<string | null>(null)
const saving = ref(false)

const timeline = ref<TimelineItem[]>([])
const attachments = ref<AttachmentPayload[]>([])
const linkedCard = ref<LinkedCardPayload | null>(null)
const detailLoading = ref(true)
const detailError = ref<string | null>(null)

const linkEditorOpen = ref(false)
const linkDraft = ref('')
const linkSaving = ref(false)

const editingDescription = ref(false)
const descriptionDraft = ref('')
const descriptionSaving = ref(false)

const editingTitle = ref(false)
const titleDraft = ref('')
const titleSaving = ref(false)

const cardFileInput = ref<HTMLInputElement | null>(null)
const attachmentBusy = ref(false)
const attachmentError = ref<string | null>(null)

async function refreshDetail(): Promise<void> {
    const detail = await boardApi.getCard(props.card.id)
    timeline.value = detail.timeline
    attachments.value = detail.attachments
    linkedCard.value = detail.linkedCard
}

async function onCommentsRefresh(): Promise<void> {
    try {
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to refresh comments and activity'
    }
}

async function loadDetail(): Promise<void> {
    detailLoading.value = true
    detailError.value = null
    try {
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to load comments and activity'
    } finally {
        detailLoading.value = false
    }
}

onMounted(loadDetail)

watch(
    () => props.realtimeTick,
    () => {
        void onCommentsRefresh()
    },
)

watch(
    () => props.card.labelIds,
    (ids) => {
        labelIds.value = [...ids]
    },
)

watch(
    () => props.card.dueDate,
    (value) => {
        dueDate.value = value
    },
)

watch(
    () => props.card.recurrence,
    (value) => {
        recurrence.value = value
    },
)

watch(
    () => props.card.checklist,
    (items) => {
        checklist.value = [...items]
    },
)

watch(
    () => props.card.description,
    (value) => {
        if (!editingDescription.value) description.value = value
    },
)

watch(
    () => props.card.title,
    (value) => {
        if (!editingTitle.value) title.value = value
    },
)

function onCardFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement
    const files = [...(input.files ?? [])]
    input.value = ''
    if (files.length > 0) {
        void uploadCardFiles(files)
    }
}

async function uploadCardFiles(files: File[]): Promise<void> {
    attachmentBusy.value = true
    attachmentError.value = null
    try {
        for (const file of files) {
            await attachmentsApi.uploadCardAttachment(props.card.id, file)
        }
    } catch (e) {
        attachmentError.value = e instanceof ApiError ? e.message : 'Failed to upload attachment'
    } finally {
        attachmentBusy.value = false
    }

    try {
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to refresh attachments'
    }
}

async function removeCardAttachment(target: AttachmentPayload): Promise<void> {
    attachmentBusy.value = true
    attachmentError.value = null
    try {
        await attachmentsApi.deleteCardAttachment(props.card.id, target.id)
        await refreshDetail()
    } catch (e) {
        attachmentError.value = e instanceof ApiError ? e.message : 'Failed to remove attachment'
    } finally {
        attachmentBusy.value = false
    }
}

function askRemoveCardAttachment(target: AttachmentPayload): void {
    confirm.require({
        header: 'Remove attachment',
        message: `Remove “${target.name}”?`,
        acceptProps: { label: 'Remove', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () => removeCardAttachment(target),
    })
}

const clipboardOpen = ref(false)

function onClipboardImage(file: File): void {
    clipboardOpen.value = false
    void uploadCardFiles([file])
}

function askArchive(): void {
    confirm.require({
        header: 'Archive card',
        message: 'Archive this card? It will disappear from the board until “Show archived cards” is enabled.',
        acceptProps: { label: 'Archive', severity: 'secondary' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: archive,
    })
}

function startEditTitle(): void {
    if (!canManage.value) {
        toast.add({
            severity: 'warn',
            summary: 'Only the card creator or a project admin can edit the title and description',
            life: 3000,
        })
        return
    }
    titleDraft.value = title.value
    editingTitle.value = true
}

function cancelEditTitle(): void {
    editingTitle.value = false
}

async function saveTitle(): Promise<void> {
    const value = titleDraft.value.trim()
    if (value === '' || titleSaving.value) return
    titleSaving.value = true
    error.value = null
    try {
        const updated = await boardApi.updateCard(props.card.id, value, description.value)
        title.value = updated.title
        emit('updated', { title: updated.title })
        editingTitle.value = false
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to save title'
    } finally {
        titleSaving.value = false
    }
}

function startEditDescription(event?: MouseEvent): void {
    const target = event?.target
    if (target instanceof HTMLElement && target.closest('a') !== null) return
    descriptionDraft.value = description.value
    editingDescription.value = true
}

function cancelEditDescription(): void {
    editingDescription.value = false
}

async function saveDescription(): Promise<void> {
    if (descriptionSaving.value) return
    descriptionSaving.value = true
    error.value = null
    try {
        const updated = await boardApi.updateCard(props.card.id, title.value, descriptionDraft.value)
        description.value = updated.description
        emit('updated', { description: updated.description })
        editingDescription.value = false
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to save description'
    } finally {
        descriptionSaving.value = false
    }
}

async function toggleLabel(labelId: string): Promise<void> {
    const next = labelIds.value.includes(labelId)
        ? labelIds.value.filter((id) => id !== labelId)
        : [...labelIds.value, labelId]
    labelIds.value = next
    error.value = null
    try {
        await boardApi.setCardLabels(props.card.id, next)
        emit('updated', { labelIds: next })
    } catch (e) {
        labelIds.value = [...props.card.labelIds]
        error.value = e instanceof ApiError ? e.message : 'Failed to update labels'
    }
}

async function changeDue(value: string | null): Promise<void> {
    dueDate.value = value
    error.value = null
    try {
        await boardApi.setCardDueDate(props.card.id, value)
        emit('updated', { dueDate: value })
    } catch (e) {
        dueDate.value = props.card.dueDate
        error.value = e instanceof ApiError ? e.message : 'Failed to set due date'
    }
}

function onDueChange(value: Date | Date[] | Array<Date | null> | null | undefined): void {
    if (value instanceof Date) {
        void changeDue(toYmd(value))
    } else if (value == null) {
        void changeDue(null)
    }
}

type ScheduleMode = 'single' | 'recurring'

const SCHEDULE_MODES: { label: string; value: ScheduleMode }[] = [
    { label: 'Single task', value: 'single' },
    { label: 'Recurring task', value: 'recurring' },
]

const RECURRENCE_KINDS: { label: string; value: RecurrenceKind }[] = [
    { label: 'Weekly', value: 'weekly' },
    { label: 'Monthly', value: 'monthly' },
]

const WEEKDAYS: { label: string; value: number }[] = [
    { label: 'Mon', value: 1 },
    { label: 'Tue', value: 2 },
    { label: 'Wed', value: 3 },
    { label: 'Thu', value: 4 },
    { label: 'Fri', value: 5 },
    { label: 'Sat', value: 6 },
    { label: 'Sun', value: 7 },
]

const MONTH_DAYS = Array.from({ length: 31 }, (_, index) => index + 1)

function defaultRule(kind: RecurrenceKind): RecurrenceRulePayload {
    return {
        kind,
        weekdays: kind === 'weekly' ? [1, 2, 3, 4, 5, 6, 7] : [],
        monthDays: kind === 'monthly' ? [1] : [],
    }
}

async function applyRecurrence(next: RecurrenceRulePayload | null): Promise<void> {
    const previous = recurrence.value
    const previousDueDate = dueDate.value
    recurrence.value = next
    if (next !== null) {
        dueDate.value = null
    }
    error.value = null
    try {
        await boardApi.setCardRecurrence(props.card.id, next)
        emit('updated', next !== null ? { recurrence: next, dueDate: null } : { recurrence: null })
        await onCommentsRefresh()
    } catch (e) {
        recurrence.value = previous
        dueDate.value = previousDueDate
        error.value = e instanceof ApiError ? e.message : 'Failed to update recurrence'
    }
}

function onKindChange(kind: RecurrenceKind): void {
    void applyRecurrence(defaultRule(kind))
}

function changeScheduleMode(mode: ScheduleMode): void {
    if (mode === scheduleMode.value) return
    void applyRecurrence(mode === 'recurring' ? defaultRule('weekly') : null)
}

function toggleNumber(values: number[], value: number): number[] {
    if (!values.includes(value)) {
        return [...values, value].sort((a, b) => a - b)
    }
    return values.length === 1 ? values : values.filter((current) => current !== value)
}

function toggleWeekday(day: number): void {
    const rule = recurrence.value
    if (rule === null) return
    void applyRecurrence({ ...rule, weekdays: toggleNumber(rule.weekdays, day) })
}

function toggleMonthDay(day: number): void {
    const rule = recurrence.value
    if (rule === null) return
    void applyRecurrence({ ...rule, monthDays: toggleNumber(rule.monthDays, day) })
}

function syncChecklist(items: ChecklistItemPayload[]): void {
    checklist.value = items
    emit('updated', { checklist: items })
}

async function startAddingChecklistItem(): Promise<void> {
    checklistAdding.value = true
    await nextTick()
    const element = checklistInput.value?.$el
    if (element instanceof HTMLInputElement) element.focus()
}

function cancelChecklistItem(): void {
    checklistAdding.value = false
    checklistText.value = ''
}

async function addChecklistItem(): Promise<void> {
    const text = checklistText.value.trim()
    if (text === '' || checklistBusy.value) return
    checklistBusy.value = true
    error.value = null
    try {
        const item = await boardApi.addChecklistItem(props.card.id, text)
        syncChecklist([...checklist.value, item])
        checklistText.value = ''
        checklistAdding.value = false
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to add checklist item'
    } finally {
        checklistBusy.value = false
    }
}

async function toggleChecklistItem(item: ChecklistItemPayload): Promise<void> {
    if (pendingChecklistItems.has(item.id)) return
    pendingChecklistItems.add(item.id)
    syncChecklist(checklist.value.map((current) => (
        current.id === item.id ? { ...current, done: !item.done } : current
    )))
    error.value = null
    try {
        await boardApi.setChecklistItemDone(props.card.id, item.id, !item.done)
    } catch (e) {
        syncChecklist(checklist.value.map((current) => (
            current.id === item.id ? { ...current, done: item.done } : current
        )))
        error.value = e instanceof ApiError ? e.message : 'Failed to update checklist item'
    } finally {
        pendingChecklistItems.delete(item.id)
    }
}

async function removeChecklistItem(item: ChecklistItemPayload): Promise<void> {
    if (pendingChecklistItems.has(item.id)) return
    const previous = checklist.value
    pendingChecklistItems.add(item.id)
    syncChecklist(checklist.value.filter((current) => current.id !== item.id))
    error.value = null
    try {
        await boardApi.removeChecklistItem(props.card.id, item.id)
    } catch (e) {
        syncChecklist(previous)
        error.value = e instanceof ApiError ? e.message : 'Failed to remove checklist item'
    } finally {
        pendingChecklistItems.delete(item.id)
    }
}

async function archive(): Promise<void> {
    saving.value = true
    error.value = null
    try {
        await boardApi.archiveCard(props.card.id)
        emit('updated', { archived: true })
        emit('close')
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to archive card'
    } finally {
        saving.value = false
    }
}

async function restore(): Promise<void> {
    saving.value = true
    error.value = null
    try {
        await boardApi.restoreCard(props.card.id)
        emit('updated', { archived: false })
        emit('close')
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to restore card'
    } finally {
        saving.value = false
    }
}

function copyCardLink(): void {
    text2Clipboard({
        text: window.location.href,
        callbackSuccess: () => toast.add({
            severity: 'info',
            summary: 'Card link copied to clipboard',
            life: 3000,
        }),
        callbackError: () => toast.add({
            severity: 'error',
            summary: 'Could not copy the link',
            life: 3000,
        }),
    })
}

function toggleLinkEditor(): void {
    linkEditorOpen.value = !linkEditorOpen.value
    linkDraft.value = ''
}

function saveLink(): void {
    const linkedCardId = parseCardRef(linkDraft.value)
    if (linkedCardId === null) {
        toast.add({ severity: 'warn', summary: 'Paste a card id or a card URL', life: 3000 })
        return
    }
    if (linkedCardId === props.card.id) {
        toast.add({ severity: 'warn', summary: 'A card cannot be linked to itself', life: 3000 })
        return
    }
    void applyLink(linkedCardId)
}

function unlinkCard(): void {
    void applyLink(null)
}

async function applyLink(linkedCardId: string | null): Promise<void> {
    if (linkSaving.value) return
    linkSaving.value = true
    error.value = null
    try {
        await boardApi.setCardLinkedCard(props.card.id, linkedCardId)
        emit('updated', { linkedCardId })
        linkEditorOpen.value = false
        await onCommentsRefresh()
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to update the card link'
    } finally {
        linkSaving.value = false
    }
}

function openLinkedCard(id: string): void {
    if (route.query.card === id) return
    void router.push({ query: { ...route.query, card: id } })
}

const markdownOpen = ref(false)
const markdownText = ref('')

function openMarkdownEditor(text: string): void {
    markdownText.value = text
    markdownOpen.value = true
}

async function saveFromMarkdownEditor(text: string): Promise<void> {
    descriptionDraft.value = text
    await saveDescription()
    markdownOpen.value = false
}
</script>

<template>
    <Modal size="xl" @close="emit('close')">
        <template #title>
            <div v-if="editingTitle" class="flex flex-wrap gap-1 md:gap-3">
                    <Textarea
                        v-model="titleDraft"
                        rows="1" auto-resize maxlength="255"
                        class="w-full resize-none text-lg font-medium"
                        id="card-title" autofocus
                        @keyup.enter="saveTitle" @keyup.esc="cancelEditTitle"/>
                <Button label="Save" size="small" outlined
                        :disabled="titleSaving || titleDraft.trim() === ''" @click="saveTitle"/>
                <Button severity="secondary" label="Cancel" size="small" @click="cancelEditTitle"/>
            </div>
            <div v-else
                 class="cursor-text rounded px-1 hover:bg-raised w-fit font-medium text-lg leading-tight"
                 title="Click to edit the title"
                 @click="startEditTitle">
                {{ title }}
            </div>
        </template>
        <div>
            <p v-if="error"
               class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                {{ error }}
            </p>

            <div v-if="props.card.archived"
                 class="rounded-lg border border-warn/40 bg-warn/10 px-3 py-2 text-warn-text">
                This card is archived.
            </div>

            <div v-if="detailError"
                 class="flex items-center justify-between gap-3 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                <span>{{ detailError }}</span>
                <Button severity="secondary" text size="small" label="Retry" class="shrink-0" @click="loadDetail"/>
            </div>
            <Divider class="my-0"/>
            <div class="grid lg:grid-cols-12">
                <div class="min-w-0 space-y-3 lg:col-span-6 lg:max-h-[70vh] lg:overflow-y-auto p-3 pl-1 border-r border-r-line">
                    <div>
                        <template v-if="canManage && editingDescription">
                            <Textarea
                                id="card-description"
                                v-model="descriptionDraft"
                                rows="4"
                                class="w-full resize-none"
                                autofocus
                                placeholder="Markdown supported"/>
                            <div class="mt-2 flex justify-end gap-2">
                                <Button text severity="secondary" outlined @click="openMarkdownEditor(descriptionDraft)">
                                    <i class="fa-solid fa-expand fa-lg"></i>
                                </Button>
                                <Button label="Save" size="small" outlined :disabled="descriptionSaving" @click="saveDescription"/>
                                <Button severity="secondary" label="Cancel" size="small" @click="cancelEditDescription"/>
                            </div>
                        </template>
                        <template v-else>
                            <div
                                v-if="description !== ''"
                                :class="['rounded-lg px-3 py-2 bg-raised/50', { 'cursor-text hover:bg-raised': canManage }, MARKDOWN_CLASS]"
                                @click="canManage && startEditDescription($event)"
                                v-html="renderMarkdown(description)"/>
                            <div
                                v-else-if="canManage"
                                class="bg-raised/50 rounded-lg px-3 py-2 text-faint hover:bg-raised"
                                @click="startEditDescription">
                                Add a description… (markdown supported)
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <span v-if="attachmentBusy" class="text-xs text-faint">Working…</span>
                            <input
                                ref="cardFileInput" type="file" multiple class="hidden"
                                :accept="MEDIA_ACCEPT" @change="onCardFilesSelected"/>
                            <Button
                                severity="primary" text size="small" label="Add files" class="p-1 pl-0"
                                :disabled="attachmentBusy" @click="cardFileInput?.click()"/>
                            <Button
                                severity="primary" text size="small" label="Clipboard" class="p-1"
                                :disabled="attachmentBusy" @click="clipboardOpen = true"/>
                        </div>
                        <p v-if="attachmentError"
                           class="mb-2 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                            {{ attachmentError }}
                        </p>
                        <div v-if="attachments.length > 0" class="space-y-1.5">
                            <AttachmentChip
                                v-for="attachment in attachments"
                                :key="attachment.id"
                                :attachment="attachment"
                                :can-delete="true"
                                @remove="askRemoveCardAttachment(attachment)"
                            />
                        </div>
                        <p v-else class="text-xs text-faint">No attachments yet.</p>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <span class="font-medium text-body">Checklist</span>
                            <span v-if="checklist.length > 0" class="text-xs text-faint">
                                {{ checklistDone }}/{{ checklist.length }}
                            </span>
                        </div>
                        <ul v-if="checklist.length > 0" class="space-y-1">
                            <li
                                v-for="item in checklist"
                                :key="item.id"
                                class="group flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-raised/60">
                                <Checkbox
                                    :model-value="item.done"
                                    binary
                                    :input-id="`checklist-${item.id}`"
                                    @update:model-value="toggleChecklistItem(item)"/>
                                <label
                                    :for="`checklist-${item.id}`"
                                    class="min-w-0 cursor-pointer break-words text-sm"
                                    :class="item.done ? 'text-faint line-through' : 'text-body'">
                                    {{ item.text }}
                                </label>
                                <button
                                    type="button"
                                    class="ml-auto shrink-0 cursor-pointer text-muted opacity-0 transition-opacity hover:text-danger-text focus:opacity-100 group-hover:opacity-100"
                                    title="Remove item"
                                    @click="removeChecklistItem(item)">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-faint">No checklist items yet.</p>
                        <!-- Add check item block -->
                        <Button
                            v-if="!checklistAdding"
                            severity="primary"
                            text
                            size="small"
                            label="Add item"
                            class="mt-2 p-1 pl-0"
                            @click="startAddingChecklistItem"/>
                        <div v-else class="mt-2 flex items-center gap-2">
                            <InputText
                                ref="checklistInput"
                                v-model="checklistText"
                                size="small"
                                class="w-full"
                                placeholder="Add a checklist item"
                                maxlength="500"
                                :disabled="checklistBusy"
                                @keyup.enter="addChecklistItem"
                                @keyup.esc="cancelChecklistItem"/>
                            <Button
                                label="Add"
                                size="small"
                                outlined
                                :disabled="checklistBusy || checklistText.trim() === ''"
                                @click="addChecklistItem"/>
                            <Button
                                label="Cancel"
                                size="small"
                                text
                                :disabled="checklistBusy"
                                @click="cancelChecklistItem"/>
                        </div>
                    </div>

                    <Divider class="my-4"/>
                    <div>
                        <p class="mb-1.5 font-medium text-body">Labels</p>
                        <div v-if="props.labels.length > 0" class="flex flex-wrap gap-1.5">
                            <button
                                v-for="label in props.labels"
                                :key="label.id"
                                type="button"
                                class="cursor-pointer rounded-full border px-2.5 py-1 text-xs transition-colors"
                                :class="labelIds.includes(label.id) ? 'font-medium text-strong' : 'border-line-strong text-muted hover:border-line-emphasis hover:text-body'"
                                :style="labelIds.includes(label.id) ? { borderColor: label.color, backgroundColor: `${label.color}26` } : undefined"
                                @click="toggleLabel(label.id)">
                                {{ label.name }}
                            </button>
                        </div>
                        <p v-else class="text-xs text-faint">No labels yet — add some via the board Labels button.</p>
                    </div>

                    <Divider class="my-4"/>
                    <CardAssignees
                        :card-id="props.card.id"
                        :members="props.members"
                        :assignee-ids="props.card.assigneeIds"
                        :executor-id="props.card.executorId"
                        @updated="emit('updated', $event)"
                        @error="error = $event"/>

                    <template v-if="!isSticker">
                        <Divider class="my-4"/>
                        <div>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="option in SCHEDULE_MODES" :key="option.value"
                                    type="button"
                                    class="cursor-pointer rounded-full border px-2.5 py-1 text-sm transition-colors"
                                    :class="scheduleMode === option.value ? 'border-brand bg-brand/15 font-medium text-brand-text' : 'border-line-strong text-muted hover:border-line-emphasis hover:text-body'"
                                    @click="changeScheduleMode(option.value)">{{ option.label }}
                                </button>
                            </div>
                            <template v-if="recurrence !== null">
                                <div class="mt-2">
                                    <Select
                                        :model-value="recurrence.kind"
                                        :options="RECURRENCE_KINDS"
                                        option-label="label"
                                        option-value="value"
                                        size="small"
                                        @update:model-value="onKindChange"/>
                                </div>
                                <div v-if="recurrence.kind === 'weekly'" class="mt-2 flex flex-wrap gap-1">
                                    <button
                                        v-for="weekday in WEEKDAYS" :key="weekday.value"
                                        type="button"
                                        class="cursor-pointer rounded-full border px-2.5 py-1 text-xs transition-colors"
                                        :class="recurrence.weekdays.includes(weekday.value) ? 'border-brand bg-brand/15 font-medium text-brand-text' : 'border-line-strong text-muted hover:border-line-emphasis hover:text-body'"
                                        @click="toggleWeekday(weekday.value)">{{ weekday.label }}
                                    </button>
                                </div>
                                <div v-else-if="recurrence.kind === 'monthly'" class="mt-2 grid grid-cols-7 gap-1">
                                    <button
                                        v-for="day in MONTH_DAYS" :key="day"
                                        type="button"
                                        class="cursor-pointer rounded-md border py-1 text-xs transition-colors"
                                        :class="recurrence.monthDays.includes(day) ? 'border-brand bg-brand/15 font-medium text-brand-text' : 'border-line-strong text-muted hover:border-line-emphasis hover:text-body'"
                                        @click="toggleMonthDay(day)">{{ day }}
                                    </button>
                                </div>
                            </template>
                            <div v-else class="mt-2">
                                <p class="mb-1 text-xs font-medium text-body">Due date</p>
                                <DatePicker
                                    :model-value="dueDateModel"
                                    date-format="yy-mm-dd"
                                    show-icon
                                    show-clear
                                    @update:model-value="onDueChange"/>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="space-y-3 p-3 pr-1 min-w-0 lg:col-span-6 lg:max-h-[70vh] lg:overflow-y-auto">
                    <CardComments
                        :card-id="props.card.id"
                        :members="props.members"
                        :columns="props.columns"
                        :is-admin="props.isAdmin"
                        :timeline="timeline"
                        :loading="detailLoading"
                        :detail-error="detailError"
                        @refresh="onCommentsRefresh"
                        @error="detailError = $event"/>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-line pt-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-3">
                        <!-- Share link -->
                        <i class="fa-solid fa-link fa-lg cursor-pointer text-muted hover:text-strong"
                           title="Copy card link"
                           @click="copyCardLink"></i>

                        <!-- Linked to another msg -->
                        <div class="flex min-w-0 items-center gap-2">
                            <i class="fa-solid fa-share-nodes fa-lg cursor-pointer text-muted hover:text-strong"
                               title="Link another card"
                               @click="toggleLinkEditor"></i>
                            <template v-if="props.card.linkedCardId !== null">
                                <button v-if="linkedCard !== null"
                                        type="button"
                                        class="max-w-60 cursor-pointer truncate text-sm text-brand-text hover:underline"
                                        :title="linkedCard.title"
                                        @click="openLinkedCard(linkedCard.id)">{{ linkedCard.title }}
                                </button>
                                <span v-else-if="!detailLoading" class="text-xs text-faint">Linked card unavailable</span>
                                <i class="fa-solid fa-xmark cursor-pointer text-muted hover:text-strong"
                                   title="Remove card link"
                                   @click="unlinkCard"></i>
                            </template>
                        </div>
                    </div>
                    <div v-if="canManage">
                        <Button
                            v-if="!props.card.archived"
                            severity="secondary"
                            size="small"
                            label="Archive"
                            @click="askArchive"
                        />
                        <Button
                            v-else
                            severity="secondary"
                            size="small"
                            label="Restore"
                            @click="restore"
                        />
                    </div>
                </div>
                <div v-if="linkEditorOpen" class="flex items-center gap-2">
                    <InputText
                        v-model="linkDraft"
                        size="small"
                        class="w-full"
                        placeholder="Paste a card id or card URL"
                        autofocus
                        :disabled="linkSaving"
                        @keyup.enter="saveLink"
                        @keyup.esc="linkEditorOpen = false"/>
                    <Button label="Link" size="small" outlined
                            :disabled="linkSaving || linkDraft.trim() === ''"
                            @click="saveLink"/>
                    <Button severity="secondary" label="Cancel" size="small" @click="linkEditorOpen = false"/>
                </div>
            </div>
        </div>
        <ClipboardPasteDialog
            v-if="clipboardOpen"
            @cancel="clipboardOpen = false"
            @done="onClipboardImage"/>
        <MarkdownEditorDialog
            v-if="markdownOpen"
            :text="markdownText"
            title="Description"
            :saving="descriptionSaving"
            @save="saveFromMarkdownEditor"
            @close="markdownOpen = false"/>
    </Modal>
</template>
