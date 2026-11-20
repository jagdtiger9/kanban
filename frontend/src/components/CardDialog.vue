<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { Divider } from 'primevue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import * as boardApi from '../api/board'
import * as attachmentsApi from '../api/attachments'
import type { AttachmentPayload } from '../api/attachments'
import type {
    ActivityPayload,
    CardPayload,
    ColumnPayload,
    CommentPayload,
    LabelPayload,
    TimelineItem,
} from '../api/board'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { text2Clipboard } from '../utils/clipboard'
import { fromYmd, toYmd } from '../utils/dates'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'
import { MAX_COMMENT_BYTES, MAX_COMMENT_FILES, MEDIA_ACCEPT } from '../utils/attachments'
import AttachmentChip from './AttachmentChip.vue'
import Modal from './Modal.vue'
import UserAvatar from './UserAvatar.vue'

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
const isCreator = computed(() => auth.user?.id === props.card.creatorId)
const canManage = computed(() => isCreator.value || props.isAdmin)
const canDeleteComment = (comment: CommentPayload): boolean =>
    auth.user?.id === comment.authorId || props.isAdmin

const title = ref(props.card.title)
const description = ref(props.card.description)
const labelIds = ref<string[]>([...props.card.labelIds])
const assigneeIds = ref<string[]>([...props.card.assigneeIds])
const dueDate = ref<string | null>(props.card.dueDate)
const dueDateModel = computed(() => fromYmd(dueDate.value))

const error = ref<string | null>(null)
const saving = ref(false)

const timeline = ref<TimelineItem[]>([])
const attachments = ref<AttachmentPayload[]>([])
const detailLoading = ref(true)
const detailError = ref<string | null>(null)

const newComment = ref('')
const commentSaving = ref(false)
const commentFiles = ref<File[]>([])
const commentFileInput = ref<HTMLInputElement | null>(null)
const editingId = ref<string | null>(null)
const editBody = ref('')

const editingDescription = ref(false)
const descriptionDraft = ref('')
const descriptionSaving = ref(false)

const editingTitle = ref(false)
const titleDraft = ref('')
const titleSaving = ref(false)

const cardFileInput = ref<HTMLInputElement | null>(null)
const attachmentBusy = ref(false)
const attachmentError = ref<string | null>(null)

const memberNames = computed(() => {
    const names = new Map<string, string>()
    for (const member of props.members) {
        names.set(member.userId, member.displayName)
    }
    return names
})

const memberAvatars = computed(() => {
    const versions = new Map<string, string | null>()
    for (const member of props.members) {
        versions.set(member.userId, member.avatarVersion)
    }
    return versions
})

function memberName(userId: string): string {
    return memberNames.value.get(userId) ?? 'Unknown member'
}

function memberAvatarVersion(userId: string): string | null {
    return memberAvatars.value.get(userId) ?? null
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleString()
}

function columnName(columnId: string): string {
    return props.columns.find((column) => column.id === columnId)?.name ?? 'Unknown column'
}

function payloadString(value: unknown): string {
    return typeof value === 'string' ? value : ''
}

function activityText(activity: ActivityPayload): string {
    const p = activity.payload
    switch (activity.type) {
        case 'card_created':
            return 'created this card'
        case 'card_archived':
            return 'archived this card'
        case 'card_restored':
            return 'restored this card'
        case 'comment_added':
            return 'added a comment'
        case 'comment_edited':
            return 'edited a comment'
        case 'card_moved': {
            const from = columnName(payloadString(p.fromColumnId))
            const to = columnName(payloadString(p.toColumnId))
            return from === to ? `reordered this card in ${from}` : `moved this card from ${from} to ${to}`
        }
        case 'card_updated': {
            const field = payloadString(p.field)
            if (field === 'description') return 'changed the description'
            const label = field === 'due_date' ? 'due date' : 'title'
            const oldValue = payloadString(p.oldValue)
            const newValue = payloadString(p.newValue)
            if (oldValue === '' && newValue !== '') return `set ${label} to ${newValue}`
            if (newValue === '' && oldValue !== '') return `removed ${label}`
            return `changed ${label} from ${oldValue} to ${newValue}`
        }
        case 'label_added':
            return `added label ${payloadString(p.labelName)}`
        case 'label_removed':
            return `removed label ${payloadString(p.labelName)}`
        case 'member_assigned':
            return `assigned ${payloadString(p.userName)}`
        case 'member_unassigned':
            return `unassigned ${payloadString(p.userName)}`
        case 'attachment_added':
            return `attached ${payloadString(p.fileName)}`
        case 'attachment_removed':
            return `removed attachment ${payloadString(p.fileName)}`
        default:
            return activity.type
    }
}

async function refreshDetail(): Promise<void> {
    const detail = await boardApi.getCard(props.card.id)
    timeline.value = detail.timeline
    attachments.value = detail.attachments
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
        refreshDetail().catch((e: unknown) => {
            detailError.value = e instanceof ApiError ? e.message : 'Failed to refresh comments and activity'
        })
    },
)

watch(
    () => props.card.labelIds,
    (ids) => {
        labelIds.value = [...ids]
    },
)

watch(
    () => props.card.assigneeIds,
    (ids) => {
        assigneeIds.value = [...ids]
    },
)

watch(
    () => props.card.dueDate,
    (value) => {
        dueDate.value = value
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

async function addComment(): Promise<void> {
    const body = newComment.value.trim()
    if (body === '' || commentSaving.value) return
    commentSaving.value = true
    detailError.value = null
    try {
        await boardApi.createComment(props.card.id, body, commentFiles.value)
        newComment.value = ''
        commentFiles.value = []
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to add comment'
    } finally {
        commentSaving.value = false
    }
}

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

function onCommentFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement
    const files = [...(input.files ?? [])]
    input.value = ''
    detailError.value = null

    const selected = [...commentFiles.value]
    for (const file of files) {
        if (selected.length >= MAX_COMMENT_FILES) {
            detailError.value = `A comment can have at most ${MAX_COMMENT_FILES} attachments`
            break
        }
        const total = selected.reduce((sum, f) => sum + f.size, 0) + file.size
        if (total > MAX_COMMENT_BYTES) {
            detailError.value = 'Comment attachments cannot exceed 50 MB in total'
            break
        }
        selected.push(file)
    }

    commentFiles.value = selected
}

function removeCommentFile(index: number): void {
    commentFiles.value = commentFiles.value.filter((_, i) => i !== index)
}

async function removeCommentAttachment(comment: CommentPayload, target: AttachmentPayload): Promise<void> {
    commentSaving.value = true
    detailError.value = null
    try {
        await attachmentsApi.deleteCommentAttachment(comment.id, target.id)
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to remove attachment'
    } finally {
        commentSaving.value = false
    }
}

function askRemoveCommentAttachment(comment: CommentPayload, target: AttachmentPayload): void {
    confirm.require({
        header: 'Remove attachment',
        message: `Remove “${target.name}”?`,
        acceptProps: { label: 'Remove', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () => removeCommentAttachment(comment, target),
    })
}

function startEdit(comment: CommentPayload): void {
    editingId.value = comment.id
    editBody.value = comment.body
}

function cancelEdit(): void {
    editingId.value = null
}

async function saveEdit(): Promise<void> {
    const body = editBody.value.trim()
    if (editingId.value === null || body === '' || commentSaving.value) return
    commentSaving.value = true
    detailError.value = null
    try {
        await boardApi.updateComment(editingId.value, body)
        editingId.value = null
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to update comment'
    } finally {
        commentSaving.value = false
    }
}

async function removeComment(target: CommentPayload): Promise<void> {
    if (commentSaving.value) return
    commentSaving.value = true
    detailError.value = null
    try {
        await boardApi.deleteComment(target.id)
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to delete comment'
    } finally {
        commentSaving.value = false
    }
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

function askDeleteComment(comment: CommentPayload): void {
    confirm.require({
        header: 'Delete comment',
        message: 'Delete this comment? This cannot be undone.',
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () => removeComment(comment),
    })
}

function startEditTitle(): void {
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

async function toggleAssignee(userId: string): Promise<void> {
    const next = assigneeIds.value.includes(userId)
        ? assigneeIds.value.filter((id) => id !== userId)
        : [...assigneeIds.value, userId]
    assigneeIds.value = next
    error.value = null
    try {
        await boardApi.setCardAssignees(props.card.id, next)
        emit('updated', { assigneeIds: next })
    } catch (e) {
        assigneeIds.value = [...props.card.assigneeIds]
        error.value = e instanceof ApiError ? e.message : 'Failed to update assignees'
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
</script>

<template>
    <Modal size="xl" @close="emit('close')">
        <template #title>
            {{ props.card.title }}
        </template>
        <template #attributes>
            <i class="fa-solid fa-share-nodes cursor-pointer text-muted hover:text-strong"
                title="Copy card link"
                @click="copyCardLink"></i>
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

            <div
                v-if="detailError"
                class="flex items-center justify-between gap-3 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                <span>{{ detailError }}</span>
                <Button
                    severity="secondary"
                    text
                    size="small"
                    label="Retry"
                    class="shrink-0"
                    @click="loadDetail"/>
            </div>
            <Divider class="my-0"/>
            <div class="grid lg:grid-cols-12">
                <div class="min-w-0 space-y-4 lg:col-span-6 lg:max-h-[70vh] lg:overflow-y-auto p-5 pl-1 border-r border-r-line">
                    <div v-if="canManage">
                        <template v-if="editingTitle">
                            <InputText
                                id="card-title"
                                v-model="titleDraft"
                                class="w-full"
                                autofocus
                                @keyup.enter="saveTitle"
                                @keyup.esc="cancelEditTitle"/>
                            <div class="mt-2 flex justify-end gap-2">
                                <Button label="Save" :disabled="titleSaving || titleDraft.trim() === ''" @click="saveTitle"/>
                                <Button severity="secondary" label="Cancel" @click="cancelEditTitle"/>
                            </div>
                        </template>
                        <div
                            v-else
                            class="cursor-text rounded-lg bg-raised/50 px-3 py-2 text-body hover:bg-raised"
                            @click="startEditTitle">
                            {{ title }}
                        </div>
                    </div>
                    <p v-else class="text-xs text-faint">Only the card creator or a project admin can edit the title and description.</p>

                    <div>
                        <label for="card-description" class="mb-1 block font-medium text-body">Description</label>
                        <template v-if="canManage && editingDescription">
                            <Textarea
                                id="card-description"
                                v-model="descriptionDraft"
                                rows="4"
                                class="w-full resize-none"
                                autofocus
                                placeholder="Markdown supported"/>
                            <div class="mt-2 flex justify-end gap-2">
                                <Button label="Save" :disabled="descriptionSaving" @click="saveDescription"/>
                                <Button severity="secondary" label="Cancel" @click="cancelEditDescription"/>
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

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <div class="font-medium text-body">Attachments</div>
                            <div class="flex items-center gap-2">
                                <span v-if="attachmentBusy" class="text-xs text-faint">Working…</span>
                                <input
                                    ref="cardFileInput"
                                    type="file"
                                    multiple
                                    class="hidden"
                                    :accept="MEDIA_ACCEPT"
                                    @change="onCardFilesSelected"
                                />
                                <Button
                                    severity="secondary"
                                    size="small"
                                    label="Add files"
                                    :disabled="attachmentBusy"
                                    @click="cardFileInput?.click()"
                                />
                            </div>
                        </div>
                        <p
                            v-if="attachmentError"
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

                    <div>
                        <p class="mb-1.5 font-medium text-body">Assignees</p>
                        <div v-if="props.members.length > 0" class="max-h-40 space-y-0.5 overflow-y-auto">
                            <label
                                v-for="member in props.members"
                                :key="member.userId"
                                class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-body transition-colors hover:bg-raised">
                                <Checkbox
                                    :model-value="assigneeIds.includes(member.userId)"
                                    :binary="true"
                                    @update:model-value="toggleAssignee(member.userId)"/>
                                <UserAvatar
                                    :user-id="member.userId"
                                    :name="member.displayName"
                                    :avatar-version="member.avatarVersion"/>
                                {{ member.displayName }}
                            </label>
                        </div>
                        <p v-else class="text-xs text-faint">No members.</p>
                    </div>

                    <div>
                        <p class="mb-1.5 font-medium text-body">Due date</p>
                        <DatePicker
                            :model-value="dueDateModel"
                            date-format="yy-mm-dd"
                            show-icon
                            show-clear
                            @update:model-value="onDueChange"
                        />
                    </div>
                </div>

                <aside class="min-w-0 space-y-3 lg:col-span-6 lg:max-h-[70vh] lg:overflow-y-auto p-5 pr-1">
                    <p class="font-medium text-body">Comments &amp; activity</p>
                    <div>
                        <Textarea
                            v-model="newComment"
                            rows="3"
                            class="w-full resize-none"
                            placeholder="Write a comment… (markdown supported)"
                        />
                        <input
                            ref="commentFileInput"
                            type="file"
                            multiple
                            class="hidden"
                            :accept="MEDIA_ACCEPT"
                            @change="onCommentFilesSelected"
                        />
                        <div v-if="commentFiles.length > 0" class="mt-1.5 flex flex-wrap gap-1.5">
                            <span
                                v-for="(file, index) in commentFiles"
                                :key="`${file.name}-${index}`"
                                class="flex items-center gap-1 rounded-full bg-active px-2 py-0.5 text-xs text-body">
                                {{ file.name }}
                                <button
                                    type="button"
                                    class="cursor-pointer text-faint hover:text-strong"
                                    :aria-label="`Remove ${file.name}`"
                                    @click="removeCommentFile(index)">
                                    ×
                                </button>
                            </span>
                        </div>
                        <div class="mt-1.5 flex items-center justify-end gap-2">
                            <Button
                                severity="secondary"
                                text
                                size="small"
                                label="Attach files"
                                :disabled="commentSaving"
                                @click="commentFileInput?.click()"
                            />
                            <Button
                                label="Add comment"
                                :disabled="commentSaving || newComment.trim() === ''"
                                @click="addComment"
                            />
                        </div>
                    </div>

                    <p v-if="detailLoading" class="text-xs text-faint">Loading…</p>
                    <p v-else-if="timeline.length === 0 && !detailError" class="text-xs text-faint">No comments or activity yet.</p>
                    <div v-else class="space-y-3">
                        <template
                            v-for="item in timeline"
                            :key="item.kind === 'comment' ? `comment-${item.comment.id}` : `event-${item.event.id}`">
                            <div v-if="item.kind === 'comment'" class="rounded-lg bg-raised/50 px-3 py-2">
                                <div class="mb-1 flex items-center gap-2">
                                    <UserAvatar
                                        :user-id="item.comment.authorId"
                                        :name="memberName(item.comment.authorId)"
                                        :avatar-version="memberAvatarVersion(item.comment.authorId)"/>
                                    <span class="font-medium text-strong">{{ memberName(item.comment.authorId) }}</span>
                                    <span v-if="item.comment.updatedAt !== item.comment.createdAt" class="text-xs text-faint">
                                        edited
                                    </span>
                                    <span class="ml-auto shrink-0 text-xs text-faint">{{ formatTime(item.comment.createdAt) }}</span>
                                </div>
                                <div v-if="editingId === item.comment.id" class="pl-8">
                                    <Textarea v-model="editBody" rows="3" class="w-full resize-none" autofocus/>
                                    <div class="mt-1.5 flex justify-end gap-2">
                                        <Button
                                            label="Save"
                                            :disabled="commentSaving || editBody.trim() === ''"
                                            @click="saveEdit"/>
                                        <Button severity="secondary" label="Cancel" @click="cancelEdit"/>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    :class="['pl-8 text-body', MARKDOWN_CLASS]"
                                    v-html="renderMarkdown(item.comment.body)"/>
                                <div
                                    v-if="item.comment.attachments.length > 0"
                                    class="mt-1.5 space-y-1.5 pl-8">
                                    <AttachmentChip
                                        v-for="attachment in item.comment.attachments"
                                        :key="attachment.id"
                                        :attachment="attachment"
                                        :can-delete="canDeleteComment(item.comment)"
                                        @remove="askRemoveCommentAttachment(item.comment, attachment)"
                                    />
                                </div>
                                <div
                                    v-if="canDeleteComment(item.comment) && editingId !== item.comment.id"
                                    class="mt-1 flex gap-3 pl-8">
                                    <Button
                                        v-if="auth.user?.id === item.comment.authorId"
                                        severity="secondary"
                                        text
                                        size="small"
                                        label="Edit"
                                        @click="startEdit(item.comment)"
                                    />
                                    <Button
                                        severity="danger"
                                        text
                                        size="small"
                                        label="Delete"
                                        @click="askDeleteComment(item.comment)"
                                    />
                                </div>
                            </div>
                            <div v-else class="flex items-start gap-2 px-3">
                                <UserAvatar
                                    :user-id="item.event.actorId ?? ''"
                                    :name="item.event.actorId ? memberName(item.event.actorId) : null"
                                    :avatar-version="item.event.actorId ? memberAvatarVersion(item.event.actorId) : null"
                                    class="mt-0.5"
                                />
                                <p class="min-w-0 flex-1 text-body">
                                    <span class="font-medium text-strong">{{ item.event.actorId ? memberName(item.event.actorId) : 'System' }}</span>
                                    {{ activityText(item.event) }}
                                    <span class="ml-1 text-xs text-faint">{{ formatTime(item.event.createdAt) }}</span>
                                </p>
                            </div>
                        </template>
                    </div>
                </aside>
            </div>

            <div v-if="canManage" class="border-t border-line pt-3">
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
    </Modal>
</template>
