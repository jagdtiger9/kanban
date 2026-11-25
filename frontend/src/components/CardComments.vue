<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import * as attachmentsApi from '../api/attachments'
import type { AttachmentPayload } from '../api/attachments'
import * as boardApi from '../api/board'
import type { ActivityPayload, ColumnPayload, CommentPayload, TimelineItem } from '../api/board'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'
import { MAX_COMMENT_BYTES, MAX_COMMENT_FILES, MEDIA_ACCEPT } from '../utils/attachments'
import AttachmentChip from './AttachmentChip.vue'
import ClipboardPasteDialog from './ClipboardPasteDialog.vue'
import MarkdownEditorDialog from './MarkdownEditorDialog.vue'
import UserAvatar from './UserAvatar.vue'

const props = defineProps<{
    cardId: string
    members: MemberPayload[]
    columns: ColumnPayload[]
    isAdmin: boolean
    timeline: TimelineItem[]
    loading: boolean
    detailError: string | null
}>()

const emit = defineEmits<{
    refresh: []
    error: [message: string | null]
}>()

const auth = useAuthStore()
const confirm = useConfirm()
const canDeleteComment = (comment: CommentPayload): boolean =>
    auth.user?.id === comment.authorId || props.isAdmin

const newComment = ref('')
const commentSaving = ref(false)
const commentFiles = ref<File[]>([])
const commentFileInput = ref<HTMLInputElement | null>(null)
const editingId = ref<string | null>(null)
const editBody = ref('')

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

const memberColors = computed(() => {
    const colors = new Map<string, string | null>()
    for (const member of props.members) {
        colors.set(member.userId, member.avatarColor)
    }
    return colors
})

function memberName(userId: string): string {
    return memberNames.value.get(userId) ?? 'Unknown member'
}

function memberAvatarVersion(userId: string): string | null {
    return memberAvatars.value.get(userId) ?? null
}

function memberColor(userId: string): string | null {
    return memberColors.value.get(userId) ?? null
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
            if (field === 'linked_card') {
                return payloadString(p.newValue) === '' ? 'removed the linked card' : 'linked another card'
            }
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

const timeLineFilter = ref<'all' | 'comment' | 'event'>('all')
const timelinePrint = computed((): TimelineItem[] => {
    if (timeLineFilter.value === 'all') {
        return props.timeline
    }
    return props.timeline.filter((item: TimelineItem) => item.kind === timeLineFilter.value)
})

async function addComment(): Promise<void> {
    const body = newComment.value.trim()
    if (body === '' || commentSaving.value) return
    commentSaving.value = true
    emit('error', null)
    try {
        await boardApi.createComment(props.cardId, body, commentFiles.value)
        newComment.value = ''
        commentFiles.value = []
        emit('refresh')
    } catch (e) {
        emit('error', e instanceof ApiError ? e.message : 'Failed to add comment')
    } finally {
        commentSaving.value = false
    }
}

function onCommentFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement
    const files = [...(input.files ?? [])]
    input.value = ''
    addCommentFiles(files)
}

const clipboardOpen = ref(false)

function onClipboardImage(file: File): void {
    clipboardOpen.value = false
    addCommentFiles([file])
}

/** One path for picked and pasted files, so the per-comment limits apply to both. */
function addCommentFiles(files: File[]): void {
    emit('error', null)

    const selected = [...commentFiles.value]
    for (const file of files) {
        if (selected.length >= MAX_COMMENT_FILES) {
            emit('error', `A comment can have at most ${MAX_COMMENT_FILES} attachments`)
            break
        }
        const total = selected.reduce((sum, f) => sum + f.size, 0) + file.size
        if (total > MAX_COMMENT_BYTES) {
            emit('error', 'Comment attachments cannot exceed 50 MB in total')
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
    emit('error', null)
    try {
        await attachmentsApi.deleteCommentAttachment(comment.id, target.id)
        emit('refresh')
    } catch (e) {
        emit('error', e instanceof ApiError ? e.message : 'Failed to remove attachment')
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
    emit('error', null)
    try {
        await boardApi.updateComment(editingId.value, body)
        editingId.value = null
        emit('refresh')
    } catch (e) {
        emit('error', e instanceof ApiError ? e.message : 'Failed to update comment')
    } finally {
        commentSaving.value = false
    }
}

async function removeComment(target: CommentPayload): Promise<void> {
    if (commentSaving.value) return
    commentSaving.value = true
    emit('error', null)
    try {
        await boardApi.deleteComment(target.id)
        emit('refresh')
    } catch (e) {
        emit('error', e instanceof ApiError ? e.message : 'Failed to delete comment')
    } finally {
        commentSaving.value = false
    }
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

type MarkdownTarget = 'newComment' | 'editComment'
const markdownTarget = ref<MarkdownTarget | null>(null)
const markdownText = ref('')

function openMarkdownEditor(target: MarkdownTarget, text: string): void {
    markdownText.value = text
    markdownTarget.value = target
}

async function saveFromMarkdownEditor(text: string): Promise<void> {
    if (markdownTarget.value === 'newComment') {
        newComment.value = text
        //await addComment()
    } else if (markdownTarget.value === 'editComment') {
        editBody.value = text
        await saveEdit()
    }
    markdownTarget.value = null
}
</script>

<template>
    <aside class="min-w-0 space-y-3 lg:col-span-6 lg:max-h-[70vh] lg:overflow-y-auto p-3">
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
                <Button text severity="secondary" @click="openMarkdownEditor('newComment', newComment)" class="py-2">
                    <i class="fa-solid fa-expand fa-lg"></i>
                </Button>
                <Button
                    severity="secondary" text size="small" label="Add files"
                    :disabled="commentSaving" @click="commentFileInput?.click()"/>
                <Button
                    severity="secondary" text size="small" label="Clipboard"
                    :disabled="commentSaving" @click="clipboardOpen = true"/>
                <Button
                    label="Save" outlined size="small"
                    @click="addComment" :disabled="commentSaving || newComment.trim() === ''"/>
            </div>
        </div>

        <p v-if="loading" class="text-xs text-faint">Loading…</p>
        <p v-else-if="timeline.length === 0 && detailError === null" class="text-xs text-faint">No comments or activity yet.</p>
        <div v-else class="space-y-3">
            <div class="flex justify-end gap-3">
                <Button text class="p-1" size="small" @click="timeLineFilter = 'all'">All</Button>
                <Button text class="p-1" size="small" @click="timeLineFilter = 'comment'">Comments</Button>
            </div>
            <template
                v-for="item in timelinePrint"
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
                        <Textarea v-model="editBody" rows="3" class="w-full resize-none" autofocus/>
                        <div class="mt-1.5 flex justify-end gap-2">
                            <Button
                                label="Save" size="small" outlined
                                @click="saveEdit" :disabled="commentSaving || editBody.trim() === ''"/>
                            <Button label="Edit" size="small" outlined @click="openMarkdownEditor('editComment', editBody)"/>
                            <Button severity="secondary" label="Cancel" size="small" @click="cancelEdit"/>
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
                            :key="attachment.id" :attachment="attachment"
                            :can-delete="canDeleteComment(item.comment)"
                            @remove="askRemoveCommentAttachment(item.comment, attachment)"/>
                    </div>
                    <div v-if="canDeleteComment(item.comment) && editingId !== item.comment.id"
                         class="flex justify-end gap-3">
                        <Button
                            v-if="auth.user?.id === item.comment.authorId" @click="startEdit(item.comment)"
                            severity="secondary" text size="small" label="Edit"/>
                        <Button severity="danger" text size="small" @click="askDeleteComment(item.comment)">
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
                        <span class="font-medium text-strong">
                            {{ item.event.actorId ? memberName(item.event.actorId) : 'System' }}
                        </span>
                        {{ activityText(item.event) }}
                        <span class="ml-1 text-xs text-faint">{{ formatTime(item.event.createdAt) }}</span>
                    </p>
                </div>
            </template>
        </div>
    </aside>
    <ClipboardPasteDialog
        v-if="clipboardOpen"
        @cancel="clipboardOpen = false"
        @done="onClipboardImage"/>
    <MarkdownEditorDialog
        v-if="markdownTarget !== null"
        :text="markdownText"
        title="Comment"
        :saving="commentSaving"
        @save="saveFromMarkdownEditor"
        @close="markdownTarget = null"/>
</template>
