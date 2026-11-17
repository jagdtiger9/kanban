<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import * as boardApi from '../api/board'
import type {
    ActivityPayload,
    CardPayload,
    ColumnPayload,
    CommentPayload,
    LabelPayload,
} from '../api/board'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { fromYmd, toYmd } from '../utils/dates'
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

const comments = ref<CommentPayload[]>([])
const activities = ref<ActivityPayload[]>([])
const detailLoading = ref(true)
const detailError = ref<string | null>(null)

const newComment = ref('')
const commentSaving = ref(false)
const editingId = ref<string | null>(null)
const editBody = ref('')

const memberNames = computed(() => {
    const names = new Map<string, string>()
    for (const member of props.members) {
        names.set(member.userId, member.displayName)
    }
    return names
})

const activitiesNewest = computed(() => [...activities.value].reverse())

function memberName(userId: string): string {
    return memberNames.value.get(userId) ?? 'Unknown member'
}

function initials(name: string | undefined): string {
    return name ? name.slice(0, 1).toUpperCase() : '?'
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

function renderMarkdown(markdown: string): string {
    return DOMPurify.sanitize(marked.parse(markdown, { async: false }))
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
        default:
            return activity.type
    }
}

async function refreshDetail(): Promise<void> {
    const detail = await boardApi.getCard(props.card.id)
    comments.value = detail.comments
    activities.value = detail.activities
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

async function addComment(): Promise<void> {
    const body = newComment.value.trim()
    if (body === '' || commentSaving.value) return
    commentSaving.value = true
    detailError.value = null
    try {
        await boardApi.createComment(props.card.id, body)
        newComment.value = ''
        await refreshDetail()
    } catch (e) {
        detailError.value = e instanceof ApiError ? e.message : 'Failed to add comment'
    } finally {
        commentSaving.value = false
    }
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
        acceptProps: { label: 'Archive', severity: 'danger' },
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

async function saveContent(): Promise<void> {
    saving.value = true
    error.value = null
    try {
        const updated = await boardApi.updateCard(props.card.id, title.value, description.value)
        emit('updated', { title: updated.title, description: updated.description })
        emit('close')
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to save card'
    } finally {
        saving.value = false
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
</script>

<template>
    <Modal size="lg" @close="emit('close')">
        <template #title>{{ props.card.title }}</template>
        <div class="space-y-4">
            <p
                v-if="error"
                class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text"
            >
                {{ error }}
            </p>

            <div
                v-if="props.card.archived"
                class="rounded-lg border border-warn/40 bg-warn/10 px-3 py-2 text-sm text-warn-text"
            >
                This card is archived.
            </div>

            <template v-if="canManage">
                <div>
                    <label for="card-title" class="mb-1 block text-sm font-medium text-body">Title</label>
                    <InputText id="card-title" v-model="title" class="w-full"/>
                </div>
                <div>
                    <label for="card-description" class="mb-1 block text-sm font-medium text-body">Description</label>
                    <Textarea id="card-description" v-model="description" rows="4" class="w-full resize-none"/>
                    <div class="mt-2 flex justify-end">
                        <Button label="Save" :disabled="saving || title.trim() === ''" @click="saveContent"/>
                    </div>
                </div>
            </template>
            <template v-else>
                <p class="text-xs text-faint">Only the card creator or a project admin can edit the title and description.</p>
                <div
                    v-if="props.card.description !== ''"
                    class="whitespace-pre-wrap rounded-lg bg-raised/50 px-3 py-2 text-sm text-body"
                >
                    {{ props.card.description }}
                </div>
            </template>

            <div>
                <p class="mb-1.5 text-sm font-medium text-body">Labels</p>
                <div v-if="props.labels.length > 0" class="flex flex-wrap gap-1.5">
                    <button
                        v-for="label in props.labels"
                        :key="label.id"
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-xs transition-colors"
                        :class="
              labelIds.includes(label.id)
                ? 'font-medium text-strong'
                : 'border-line-strong text-muted hover:border-line-emphasis hover:text-body'
            "
                        :style="
              labelIds.includes(label.id)
                ? { borderColor: label.color, backgroundColor: `${label.color}26` }
                : undefined
            "
                        @click="toggleLabel(label.id)"
                    >
                        {{ label.name }}
                    </button>
                </div>
                <p v-else class="text-xs text-faint">No labels yet — add some via the board Labels button.</p>
            </div>

            <div>
                <p class="mb-1.5 text-sm font-medium text-body">Assignees</p>
                <div v-if="props.members.length > 0" class="max-h-40 space-y-0.5 overflow-y-auto">
                    <label
                        v-for="member in props.members"
                        :key="member.userId"
                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm text-body transition-colors hover:bg-raised"
                    >
                        <Checkbox
                            :model-value="assigneeIds.includes(member.userId)"
                            :binary="true"
                            @update:model-value="toggleAssignee(member.userId)"
                        />
                        {{ member.displayName }}
                    </label>
                </div>
                <p v-else class="text-xs text-faint">No members.</p>
            </div>

            <div>
                <p class="mb-1.5 text-sm font-medium text-body">Due date</p>
                <DatePicker
                    :model-value="dueDateModel"
                    date-format="yy-mm-dd"
                    show-icon
                    show-clear
                    @update:model-value="onDueChange"
                />
            </div>

            <div class="border-t border-line pt-3">
                <p class="mb-2 text-sm font-medium text-body">Comments</p>
                <div
                    v-if="detailError"
                    class="mb-2 flex items-center justify-between gap-3 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text"
                >
                    <span>{{ detailError }}</span>
                    <Button
                        severity="secondary"
                        text
                        size="small"
                        label="Retry"
                        class="shrink-0"
                        @click="loadDetail"
                    />
                </div>
                <div class="space-y-3">
                    <div
                        v-for="comment in comments"
                        :key="comment.id"
                        class="rounded-lg bg-raised/50 px-3 py-2"
                    >
                        <div class="mb-1 flex items-center gap-2">
              <span
                  class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-active text-[10px] text-strong"
              >
                {{ initials(memberName(comment.authorId)) }}
              </span>
                            <span class="text-sm font-medium text-strong">{{ memberName(comment.authorId) }}</span>
                            <span v-if="comment.updatedAt !== comment.createdAt" class="text-xs text-faint">
                edited
              </span>
                            <span class="ml-auto shrink-0 text-xs text-faint">{{ formatTime(comment.createdAt) }}</span>
                        </div>
                        <div v-if="editingId === comment.id" class="pl-8">
                            <Textarea v-model="editBody" rows="3" class="w-full resize-none" autofocus/>
                            <div class="mt-1.5 flex justify-end gap-2">
                                <Button
                                    label="Save"
                                    :disabled="commentSaving || editBody.trim() === ''"
                                    @click="saveEdit"
                                />
                                <Button severity="secondary" label="Cancel" @click="cancelEdit"/>
                            </div>
                        </div>
                        <div
                            v-else
                            class="pl-8 text-sm text-body [&_a]:text-brand-text [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-line-emphasis [&_blockquote]:pl-3 [&_blockquote]:text-muted [&_code]:rounded [&_code]:bg-raised [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-xs [&_h1]:text-base [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_hr]:my-2 [&_hr]:border-line-strong [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-1.5 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-raised [&_pre]:p-2 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:pl-5"
                            v-html="renderMarkdown(comment.body)"
                        />
                        <div
                            v-if="canDeleteComment(comment) && editingId !== comment.id"
                            class="mt-1 flex gap-3 pl-8"
                        >
                            <Button
                                v-if="auth.user?.id === comment.authorId"
                                severity="secondary"
                                text
                                size="small"
                                label="Edit"
                                @click="startEdit(comment)"
                            />
                            <Button
                                severity="danger"
                                text
                                size="small"
                                label="Delete"
                                @click="askDeleteComment(comment)"
                            />
                        </div>
                    </div>
                    <p v-if="detailLoading" class="text-xs text-faint">Loading…</p>
                    <p v-else-if="comments.length === 0 && !detailError" class="text-xs text-faint">No comments yet.</p>
                </div>
                <div class="mt-3">
          <Textarea
              v-model="newComment"
              rows="3"
              class="w-full resize-none"
              placeholder="Write a comment… (markdown supported)"
          />
                    <div class="mt-1.5 flex justify-end">
                        <Button
                            label="Add comment"
                            :disabled="commentSaving || newComment.trim() === ''"
                            @click="addComment"
                        />
                    </div>
                </div>
            </div>

            <div class="border-t border-line pt-3">
                <p class="mb-2 text-sm font-medium text-body">Activity</p>
                <p v-if="detailLoading" class="text-xs text-faint">Loading…</p>
                <p v-else-if="activitiesNewest.length === 0 && !detailError" class="text-xs text-faint">No activity yet.</p>
                <div v-else class="space-y-2.5">
                    <div v-for="activity in activitiesNewest" :key="activity.id" class="flex items-start gap-2">
            <span
                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-active text-[10px] text-strong"
            >
              {{ initials(activity.actorId ? memberName(activity.actorId) : undefined) }}
            </span>
                        <p class="min-w-0 flex-1 text-sm text-body">
              <span class="font-medium text-strong">
                {{ activity.actorId ? memberName(activity.actorId) : 'System' }}
              </span>
                            {{ activityText(activity) }}
                            <span class="ml-1 text-xs text-faint">{{ formatTime(activity.createdAt) }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="canManage" class="border-t border-line pt-3">
                <Button
                    v-if="!props.card.archived"
                    severity="danger"
                    outlined
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
