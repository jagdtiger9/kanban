<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type ComponentPublicInstance } from 'vue'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import { Divider } from 'primevue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import * as boardApi from '../api/board'
import * as attachmentsApi from '../api/attachments'
import type { AttachmentPayload } from '../api/attachments'
import type { CardPayload, ColumnPayload, LabelPayload, TimelineItem } from '../api/board'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useUnsavedEditsGuard } from '../composables/useUnsavedEdits'
import { useAuthStore } from '../stores/auth'
import { text2Clipboard } from '../utils/clipboard'
import { fromYmd, toYmd } from '../utils/dates'
import { MEDIA_ACCEPT } from '../utils/attachments'
import AttachmentChip from './AttachmentChip.vue'
import CardTimeline from './CardTimeline.vue'
import ClipboardPasteDialog from './ClipboardPasteDialog.vue'
import MarkdownField from './MarkdownField.vue'
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

// Closing (Escape, ×, mask) asks first when any field below holds unsaved edits.
const unsaved = useUnsavedEditsGuard(() => emit('close'))

const title = ref(props.card.title)
const description = ref(props.card.description)
const labelIds = ref<string[]>([...props.card.labelIds])
const assigneeIds = ref<string[]>([...props.card.assigneeIds])
const dueDate = ref<string | null>(props.card.dueDate)
const dueDateModel = computed(() => fromYmd(dueDate.value))

const error = ref<string | null>(null)
const saving = ref(false)

// ---- card detail: timeline and attachments --------------------------------

const timeline = ref<TimelineItem[]>([])
const attachments = ref<AttachmentPayload[]>([])
const detailLoading = ref(true)
const detailError = ref<string | null>(null)

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
        // An open draft lives in its MarkdownField; the saved text can follow the server.
        description.value = value
    },
)

watch(
    () => props.card.title,
    (value) => {
        if (!editingTitle.value) title.value = value
    },
)

// ---- title: Enter or leaving the field saves (Trello-like) ----------------

const editingTitle = ref(false)
const titleDraft = ref('')
const titleSaving = ref(false)
const titleInput = ref<ComponentPublicInstance | null>(null)
const titleDirty = (): boolean => editingTitle.value && titleDraft.value.trim() !== title.value

async function startEditTitle(): Promise<void> {
    titleDraft.value = title.value
    editingTitle.value = true
    // `autofocus` does not fire for a field inserted after page load; without the
    // focus there is no blur, and clicking outside would not leave the editor.
    await nextTick()
    const element = titleInput.value?.$el as HTMLTextAreaElement | undefined
    element?.focus()
}

function cancelEditTitle(): void {
    editingTitle.value = false
}

/** Leaving the title field saves it; an empty or unchanged title is dropped. */
function onTitleBlur(): void {
    // The close prompt took the focus: it decides about the title itself.
    if (!editingTitle.value || unsaved.prompting.value) return
    const value = titleDraft.value.trim()
    if (value === '' || value === title.value) {
        cancelEditTitle()
        return
    }
    void saveTitle()
}

async function saveTitle(): Promise<boolean> {
    const value = titleDraft.value.trim()
    if (value === '' || titleSaving.value) return false
    titleSaving.value = true
    error.value = null
    try {
        const updated = await boardApi.updateCard(props.card.id, value, description.value)
        title.value = updated.title
        emit('updated', { title: updated.title })
        editingTitle.value = false
        return true
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to save title'
        return false
    } finally {
        titleSaving.value = false
    }
}

onBeforeUnmount(
    unsaved.register({ label: 'title', isDirty: titleDirty, save: saveTitle, discard: cancelEditTitle }),
)

// ---- description -----------------------------------------------------------

const descriptionSaving = ref(false)

async function saveDescription(text: string): Promise<boolean> {
    if (descriptionSaving.value) return false
    descriptionSaving.value = true
    error.value = null
    try {
        const updated = await boardApi.updateCard(props.card.id, title.value, text)
        description.value = updated.description
        emit('updated', { description: updated.description })
        return true
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to save description'
        return false
    } finally {
        descriptionSaving.value = false
    }
}

// ---- card attachments ------------------------------------------------------

const cardFileInput = ref<HTMLInputElement | null>(null)
const attachmentBusy = ref(false)
const attachmentError = ref<string | null>(null)
const clipboardOpen = ref(false)

function onCardFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement
    const files = [...(input.files ?? [])]
    input.value = ''
    if (files.length > 0) {
        void uploadCardFiles(files)
    }
}

function onClipboardImage(file: File): void {
    clipboardOpen.value = false
    void uploadCardFiles([file])
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

// ---- labels, assignees, due date -------------------------------------------

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

// ---- archive, restore, link --------------------------------------------------

function askArchive(): void {
    confirm.require({
        header: 'Archive card',
        message: 'Archive this card? It will disappear from the board until “Show archived cards” is enabled.',
        acceptProps: { label: 'Archive', severity: 'secondary' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: archive,
    })
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
    <Modal size="xl" @close="unsaved.requestClose">
        <template #title>
            <!-- The title is edited in place (Trello-like): grows with the text,
                 Enter or leaving the field saves, a title has no line breaks. -->
            <Textarea
                v-if="editingTitle"
                id="card-title"
                ref="titleInput"
                v-model="titleDraft"
                rows="1"
                auto-resize
                maxlength="255"
                aria-label="Card title"
                class="w-full resize-none text-lg leading-tight font-medium"
                @keydown.enter.prevent="saveTitle"
                @blur="onTitleBlur"/>
            <span
                v-else-if="canManage"
                class="-mx-1 block cursor-text rounded px-1 text-lg leading-tight font-medium hover:bg-raised"
                title="Click to edit the title"
                @click="startEditTitle">
                {{ title }}
            </span>
            <span v-else class="text-lg leading-tight font-medium">{{ title }}</span>
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
                    <p v-if="!canManage" class="text-xs text-faint">Only the card creator or a project admin can edit the title and description.</p>

                    <div>
                        <label for="card-description" class="section-heading mb-1 block">Description</label>
                        <MarkdownField
                            :value="description"
                            :save="saveDescription"
                            :saving="descriptionSaving"
                            :editable="canManage"
                            :rows="4"
                            input-id="card-description"
                            editor-title="Description"
                            unsaved-label="description"
                            empty-text="Add a description… (markdown supported)"/>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <div class="section-heading">Attachments</div>
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
                                    text
                                    size="small"
                                    title="Add files"
                                    aria-label="Add files"
                                    :disabled="attachmentBusy"
                                    @click="cardFileInput?.click()">
                                    <i class="fa-solid fa-paperclip"></i>
                                </Button>
                                <Button
                                    text
                                    size="small"
                                    title="Paste image from clipboard"
                                    aria-label="Paste image from clipboard"
                                    :disabled="attachmentBusy"
                                    @click="clipboardOpen = true">
                                    <i class="fa-solid fa-paste"></i>
                                </Button>
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
                        <p class="section-heading mb-1.5">Labels</p>
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

                    <!-- Assignees and due date side by side, so the date is not lost at the bottom. -->
                    <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <p class="section-heading mb-1.5">Assignees</p>
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

                    <div class="min-w-0">
                        <p class="section-heading mb-1.5">Due date</p>
                        <DatePicker
                            :model-value="dueDateModel"
                            date-format="yy-mm-dd"
                            show-icon
                            show-clear
                            fluid
                            @update:model-value="onDueChange"
                        />
                    </div>
                    </div>
                </div>

                <aside class="min-w-0 lg:col-span-6 lg:max-h-[70vh] lg:overflow-y-auto p-5 pr-1">
                    <CardTimeline
                        :card-id="props.card.id"
                        :timeline="timeline"
                        :loading="detailLoading"
                        :members="props.members"
                        :columns="props.columns"
                        :is-admin="props.isAdmin"
                        :refresh="refreshDetail"/>
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
        <ClipboardPasteDialog
            v-if="clipboardOpen"
            @cancel="clipboardOpen = false"
            @done="onClipboardImage"/>
    </Modal>
</template>
