<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, onMounted, ref, useId, watch, type ComponentPublicInstance } from 'vue'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import { useUnsavedEditsSource } from '../composables/useUnsavedEdits'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'

// md-editor-v3 is heavy: load it only when the full editor is opened.
const MarkdownEditorDialog = defineAsyncComponent(() => import('./MarkdownEditorDialog.vue'))

/**
 * One markdown text field for the card dialog (description, comment edit, new
 * comment), Trello-like:
 * - view mode shows the rendered text (or an empty-state line); a click opens the editor;
 * - leaving the field does not save: an unchanged text just closes, a changed one
 *   keeps its draft with an "unsaved edits" mark until it is saved or discarded;
 * - the full editor (text left, rendered preview right) edits the same draft.
 *
 * mode "composer" (new comment): nothing is saved yet, so the value is empty, the
 * draft itself is shown in view mode, and a successful save clears the field.
 * The parent persists the text through `save`. With `unsavedLabel` the field joins
 * the surrounding dialog's unsaved-edits guard (useUnsavedEdits) on its own.
 */
const props = withDefaults(
    defineProps<{
        value: string
        save: (text: string) => Promise<boolean>
        editorTitle: string
        mode?: 'field' | 'composer'
        editable?: boolean
        startEditing?: boolean
        keepOpen?: boolean
        saving?: boolean
        inputId?: string
        rows?: number
        placeholder?: string
        emptyText?: string
        unsavedLabel?: string
    }>(),
    {
        mode: 'field',
        editable: true,
        startEditing: false,
        keepOpen: false,
        saving: false,
        inputId: undefined,
        rows: 3,
        placeholder: 'Markdown supported',
        emptyText: '',
        unsavedLabel: '',
    },
)

/**
 * done: back in a clean view mode (saved, unchanged or discarded);
 * dirtyChange: the field started or stopped holding unsaved edits.
 */
const emit = defineEmits<{ done: []; dirtyChange: [dirty: boolean] }>()

const composer = computed(() => props.mode === 'composer')

const editing = ref(false)
const draft = ref('')
const hasDraft = ref(false)
const editorOpen = ref(false)
const fieldId = props.inputId ?? useId()
const input = ref<ComponentPublicInstance | null>(null)

/** The draft differs from what is saved (for a new comment: anything typed). */
const changed = computed(() => (composer.value ? draft.value.trim() !== '' : draft.value !== props.value))
const dirty = computed(() => (editing.value || hasDraft.value) && changed.value)
const canSave = computed(() => !props.saving && (!composer.value || draft.value.trim() !== ''))

watch(dirty, (value) => emit('dirtyChange', value))

async function focusField(): Promise<void> {
    // `autofocus` does not fire for a field inserted after page load.
    await nextTick()
    const element = input.value?.$el as HTMLTextAreaElement | undefined
    element?.focus()
}

function startEdit(event?: MouseEvent): void {
    if (!props.editable || editing.value) return
    const target = event?.target
    if (target instanceof HTMLElement && target.closest('a') !== null) return
    if (!hasDraft.value) draft.value = composer.value ? '' : props.value
    editing.value = true
    void focusField()
}

function onBlur(): void {
    // The full editor took the focus, or the field must stay (e.g. files attached).
    if (editorOpen.value || props.keepOpen) return
    editing.value = false
    if (changed.value) {
        hasDraft.value = true
    } else {
        hasDraft.value = false
        emit('done')
    }
}

async function saveDraft(): Promise<boolean> {
    if (!canSave.value) return false
    const ok = await props.save(draft.value)
    if (!ok) return false
    editorOpen.value = false
    hasDraft.value = false
    editing.value = false
    if (composer.value) draft.value = ''
    emit('done')
    return true
}

function discard(): void {
    editorOpen.value = false
    hasDraft.value = false
    editing.value = false
    draft.value = ''
    emit('done')
}

function openEditor(): void {
    editorOpen.value = true
}

function closeEditor(): void {
    editorOpen.value = false
    if (editing.value) void focusField()
}

// Something attached from outside (a file picked or pasted): show the editor with it.
watch(
    () => props.keepOpen,
    (keep) => {
        if (keep) startEdit()
    },
)

onMounted(() => {
    if (props.startEditing) startEdit()
})

if (props.unsavedLabel !== '') {
    useUnsavedEditsSource({ label: props.unsavedLabel, isDirty: () => dirty.value, save: saveDraft, discard })
}
</script>

<template>
    <div>
        <template v-if="editing">
            <Textarea
                ref="input"
                :id="fieldId"
                v-model="draft"
                :rows="props.rows"
                class="w-full resize-none"
                :placeholder="props.placeholder"
                @blur="onBlur"/>
            <slot name="below"/>
            <!-- mousedown.prevent: the buttons must not blur the field (= leave the
                 editor) before their click lands. -->
            <div class="mt-1 flex items-center justify-end gap-1">
                <slot name="actions"/>
                <Button text size="small"
                        title="Open full editor" aria-label="Open full editor"
                        @mousedown.prevent
                        @click="openEditor">
                    <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                </Button>
                <Button text size="small" title="Save" aria-label="Save" :disabled="!canSave"
                        @mousedown.prevent
                        @click="saveDraft">
                    <i class="fa-solid fa-floppy-disk fa-lg"></i>
                </Button>
            </div>
            <MarkdownEditorDialog
                v-if="editorOpen"
                v-model="draft"
                :title="props.editorTitle"
                :saving="props.saving"
                @save="saveDraft"
                @close="closeEditor"/>
        </template>
        <template v-else>
            <!-- A new comment has nothing saved: show its draft instead. -->
            <div
                v-if="composer && hasDraft"
                :class="['cursor-text rounded-lg px-3 py-2 bg-raised/50 hover:bg-raised', MARKDOWN_CLASS]"
                @click="startEdit($event)"
                v-html="renderMarkdown(draft)"/>
            <div
                v-else-if="props.value !== ''"
                :class="['rounded-lg px-3 py-2 bg-raised/50', { 'cursor-text hover:bg-raised': props.editable }, MARKDOWN_CLASS]"
                @click="startEdit($event)"
                v-html="renderMarkdown(props.value)"/>
            <div
                v-else-if="props.editable && props.emptyText !== ''"
                class="cursor-text rounded-lg bg-raised/50 px-3 py-2 text-faint hover:bg-raised"
                @click="startEdit()">
                {{ props.emptyText }}
            </div>
            <p v-if="hasDraft" class="mt-1 flex gap-3 text-xs text-warn-text">
                <span>Unsaved edits</span>
                <button type="button" class="cursor-pointer underline" @click="startEdit()">Continue</button>
                <button type="button" class="cursor-pointer underline" @click="discard">Discard</button>
            </p>
        </template>
    </div>
</template>
