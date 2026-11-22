<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import * as boardApi from '../api/board'
import * as attachmentsApi from '../api/attachments'
import type { AttachmentPayload } from '../api/attachments'
import type { ActivityPayload, ColumnPayload, CommentPayload, TimelineItem } from '../api/board'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'
import { MAX_COMMENT_BYTES, MAX_COMMENT_FILES, MEDIA_ACCEPT } from '../utils/attachments'
import AttachmentChip from './AttachmentChip.vue'
import ClipboardPasteDialog from './ClipboardPasteDialog.vue'
import MarkdownField from './MarkdownField.vue'
import UserAvatar from './UserAvatar.vue'

/**
 * Right column of the card dialog: the new-comment composer and the timeline of
 * comments and activity, filtered All / Comments / Activity. The parent owns the
 * data (it loads the card detail together with the attachments); after any change
 * this component calls `refresh` to reload it.
 */
const props = defineProps<{
    cardId: string
    timeline: TimelineItem[]
    loading: boolean
    members: MemberPayload[]
    columns: ColumnPayload[]
    isAdmin: boolean
    refresh: () => Promise<void>
}>()

const auth = useAuthStore()
const confirm = useConfirm()
const toast = useToast()

const error = ref<string | null>(null)
const saving = ref(false)

// ---- filter: All / Comments / Activity ---------------------------------

type TimelineFilter = 'all' | 'comment' | 'event'
const FILTER_KEY = 'kanban.card.timelineFilter'
const FILTERS: { value: TimelineFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'comment', label: 'Comments' },
    { value: 'event', label: 'Activity' },
]

/** Per-viewer convenience: the last choice is remembered in this browser only. */
function loadFilter(): TimelineFilter {
    try {
        const saved = localStorage.getItem(FILTER_KEY)
        if (saved === 'all' || saved === 'comment' || saved === 'event') return saved
    } catch {
        // storage may be unavailable (private mode); fall back to the default
    }
    return 'comment'
}

const filter = ref<TimelineFilter>(loadFilter())

function setFilter(value: TimelineFilter): void {
    filter.value = value
    try {
        localStorage.setItem(FILTER_KEY, value)
    } catch {
        // not remembered, still applied
    }
}

const counts = computed(() => {
    const comment = props.timeline.filter((item) => item.kind === 'comment').length
    return { all: props.timeline.length, comment, event: props.timeline.length - comment }
})

const visibleTimeline = computed(() =>
    filter.value === 'all' ? props.timeline : props.timeline.filter((item) => item.kind === filter.value),
)

const EMPTY_TEXT: Record<TimelineFilter, string> = {
    all: 'No comments or activity yet.',
    comment: 'No comments yet.',
    event: 'No activity yet.',
}

// ---- members and activity text -------------------------------------------

const membersById = computed(() => new Map(props.members.map((member) => [member.userId, member])))

function memberName(userId: string): string {
    return membersById.value.get(userId)?.displayName ?? 'Unknown member'
}

function memberAvatarVersion(userId: string): string | null {
    return membersById.value.get(userId)?.avatarVersion ?? null
}

function memberColor(userId: string): string | null {
    return membersById.value.get(userId)?.avatarColor ?? null
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
        case 'executor_assigned':
            return `made ${payloadString(p.userName)} the executor`
        case 'executor_unassigned':
            return `removed ${payloadString(p.userName)} as executor`
        case 'attachment_added':
            return `attached ${payloadString(p.fileName)}`
        case 'attachment_removed':
            return `removed attachment ${payloadString(p.fileName)}`
        default:
            return activity.type
    }
}

const canDeleteComment = (comment: CommentPayload): boolean => auth.user?.id === comment.authorId || props.isAdmin

/**
 * Runs a change, then reloads the timeline; errors stay in this column.
 * True once the change itself succeeded, even if the reload failed: the caller must
 * not offer to send the same comment again.
 */
async function mutate(action: () => Promise<unknown>, failure: string): Promise<boolean> {
    if (saving.value) return false
    saving.value = true
    error.value = null
    try {
        try {
            await action()
        } catch (e) {
            error.value = e instanceof ApiError ? e.message : failure
            return false
        }
        try {
            await props.refresh()
        } catch (e) {
            error.value = e instanceof ApiError ? e.message : 'Failed to refresh comments and activity'
        }
        return true
    } finally {
        saving.value = false
    }
}

// ---- new comment ------------------------------------------------------------

const commentFiles = ref<File[]>([])
const commentFileInput = ref<HTMLInputElement | null>(null)
const clipboardOpen = ref(false)

async function addComment(text: string): Promise<boolean> {
    const body = text.trim()
    if (body === '') return false
    const ok = await mutate(() => boardApi.createComment(props.cardId, body, commentFiles.value), 'Failed to add comment')
    if (ok) commentFiles.value = []
    return ok
}

function onCommentFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement
    const files = [...(input.files ?? [])]
    input.value = ''
    addCommentFiles(files)
}

function onClipboardImage(file: File): void {
    clipboardOpen.value = false
    addCommentFiles([file])
}

/** One path for picked and pasted files, so the per-comment limits apply to both. */
function addCommentFiles(files: File[]): void {
    error.value = null
    const selected = [...commentFiles.value]
    for (const file of files) {
        if (selected.length >= MAX_COMMENT_FILES) {
            error.value = `A comment can have at most ${MAX_COMMENT_FILES} attachments`
            break
        }
        const total = selected.reduce((sum, f) => sum + f.size, 0) + file.size
        if (total > MAX_COMMENT_BYTES) {
            error.value = 'Comment attachments cannot exceed 50 MB in total'
            break
        }
        selected.push(file)
    }
    commentFiles.value = selected
}

function removeCommentFile(index: number): void {
    commentFiles.value = commentFiles.value.filter((_, i) => i !== index)
}

// ---- editing and deleting comments --------------------------------------------

const editingId = ref<string | null>(null)
const editDirty = ref(false)

function startEdit(comment: CommentPayload): void {
    // The open comment's draft must not be lost silently.
    if (editingId.value !== null && editingId.value !== comment.id && editDirty.value) {
        toast.add({ severity: 'warn', summary: 'Save or discard the comment you are editing first', life: 3000 })
        return
    }
    editingId.value = comment.id
}

/** The comment's field is back in a clean view: show the comment as usual. */
function endEdit(): void {
    editingId.value = null
    editDirty.value = false
}

function saveCommentEdit(text: string): Promise<boolean> {
    const id = editingId.value
    const body = text.trim()
    if (id === null || body === '') return Promise.resolve(false)
    return mutate(() => boardApi.updateComment(id, body), 'Failed to update comment')
}

function askDeleteComment(comment: CommentPayload): void {
    confirm.require({
        header: 'Delete comment',
        message: 'Delete this comment? This cannot be undone.',
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () => mutate(() => boardApi.deleteComment(comment.id), 'Failed to delete comment'),
    })
}

function askRemoveCommentAttachment(comment: CommentPayload, target: AttachmentPayload): void {
    confirm.require({
        header: 'Remove attachment',
        message: `Remove “${target.name}”?`,
        acceptProps: { label: 'Remove', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () =>
            mutate(() => attachmentsApi.deleteCommentAttachment(comment.id, target.id), 'Failed to remove attachment'),
    })
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="section-heading">Comments &amp; activity</p>
            <div class="flex items-center gap-1 text-xs" role="group" aria-label="Show in the timeline">
                <button
                    v-for="option in FILTERS"
                    :key="option.value"
                    type="button"
                    :aria-pressed="filter === option.value"
                    :class="['cursor-pointer rounded px-1.5 py-0.5',
                             filter === option.value
                                 ? 'bg-brand/15 font-semibold text-brand-text'
                                 : 'text-muted hover:text-brand-text']"
                    @click="setFilter(option.value)">
                    {{ option.label }} {{ counts[option.value] }}
                </button>
            </div>
        </div>

        <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
            {{ error }}
        </p>

        <!-- Outside the field: it must survive the field collapsing while the file
             picker is open. -->
        <input
            ref="commentFileInput"
            type="file"
            multiple
            class="hidden"
            :accept="MEDIA_ACCEPT"
            @change="onCommentFilesSelected"/>
        <MarkdownField
            mode="composer"
            value=""
            :save="addComment"
            :saving="saving"
            :keep-open="commentFiles.length > 0"
            editor-title="Comment"
            unsaved-label="new comment"
            empty-text="Write a comment… (markdown supported)"
            placeholder="Write a comment… (markdown supported)"
            @done="commentFiles = []">
            <template #below>
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
            </template>
            <template #actions>
                <Button text size="small" title="Attach files" aria-label="Attach files" :disabled="saving"
                        @mousedown.prevent
                        @click="commentFileInput?.click()">
                    <i class="fa-solid fa-paperclip"></i>
                </Button>
                <Button text size="small" title="Paste image from clipboard" aria-label="Paste image from clipboard"
                        :disabled="saving"
                        @mousedown.prevent
                        @click="clipboardOpen = true">
                    <i class="fa-solid fa-paste"></i>
                </Button>
            </template>
        </MarkdownField>

        <p v-if="props.loading" class="text-xs text-faint">Loading…</p>
        <p v-else-if="visibleTimeline.length === 0" class="text-xs text-faint">{{ EMPTY_TEXT[filter] }}</p>
        <div v-else class="space-y-3">
            <template
                v-for="item in visibleTimeline"
                :key="item.kind === 'comment' ? `comment-${item.comment.id}` : `event-${item.event.id}`">
                <div v-if="item.kind === 'comment'" class="rounded-lg bg-raised/50 px-3 py-2">
                    <div class="mb-1 flex items-center gap-2">
                        <UserAvatar
                            :user-id="item.comment.authorId"
                            :name="memberName(item.comment.authorId)"
                            :avatar-version="memberAvatarVersion(item.comment.authorId)"
                            :color="memberColor(item.comment.authorId)"/>
                        <span class="font-medium text-strong">{{ memberName(item.comment.authorId) }}</span>
                        <span v-if="item.comment.updatedAt !== item.comment.createdAt" class="text-xs text-faint">
                            edited
                        </span>
                        <span class="ml-auto shrink-0 text-xs text-faint">{{ formatTime(item.comment.createdAt) }}</span>
                    </div>
                    <div v-if="editingId === item.comment.id" class="pl-8">
                        <MarkdownField
                            :value="item.comment.body"
                            :save="saveCommentEdit"
                            :saving="saving"
                            start-editing
                            editor-title="Comment"
                            unsaved-label="comment edit"
                            @dirty-change="editDirty = $event"
                            @done="endEdit"/>
                    </div>
                    <div
                        v-else
                        :class="['pl-8 text-body', MARKDOWN_CLASS]"
                        v-html="renderMarkdown(item.comment.body)"/>
                    <div v-if="item.comment.attachments.length > 0" class="mt-1.5 space-y-1.5 pl-8">
                        <AttachmentChip
                            v-for="attachment in item.comment.attachments"
                            :key="attachment.id"
                            :attachment="attachment"
                            :can-delete="canDeleteComment(item.comment)"
                            @remove="askRemoveCommentAttachment(item.comment, attachment)"/>
                    </div>
                    <div v-if="canDeleteComment(item.comment) && editingId !== item.comment.id" class="mt-1 flex gap-3 pl-8">
                        <Button
                            v-if="auth.user?.id === item.comment.authorId"
                            text
                            size="small"
                            title="Edit comment"
                            aria-label="Edit comment"
                            @click="startEdit(item.comment)">
                            <i class="fa-solid fa-pen"></i>
                        </Button>
                        <Button
                            severity="danger"
                            text
                            size="small"
                            title="Delete comment"
                            aria-label="Delete comment"
                            @click="askDeleteComment(item.comment)">
                            <i class="fa-solid fa-trash-can"></i>
                        </Button>
                    </div>
                </div>
                <div v-else class="flex items-start gap-2 px-3">
                    <UserAvatar
                        :user-id="item.event.actorId ?? ''"
                        :name="item.event.actorId ? memberName(item.event.actorId) : null"
                        :avatar-version="item.event.actorId ? memberAvatarVersion(item.event.actorId) : null"
                        :color="item.event.actorId ? memberColor(item.event.actorId) : null"
                        class="mt-0.5"/>
                    <p class="min-w-0 flex-1 text-body">
                        <span class="font-medium text-strong">{{ item.event.actorId ? memberName(item.event.actorId) : 'System' }}</span>
                        {{ activityText(item.event) }}
                        <span class="ml-1 text-xs text-faint">{{ formatTime(item.event.createdAt) }}</span>
                    </p>
                </div>
            </template>
        </div>

        <ClipboardPasteDialog v-if="clipboardOpen" @cancel="clipboardOpen = false" @done="onClipboardImage"/>
    </div>
</template>
