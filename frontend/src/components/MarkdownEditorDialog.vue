<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'
import MdEditor from './MdEditor.vue'

/**
 * Full-size markdown editor: text on the left, rendered preview on the right.
 * The preview uses the same renderMarkdown as the card, not md-editor's own
 * renderer, so it shows exactly what the card will show.
 * Edits the caller's draft directly, so closing keeps the text in the inline
 * field; Save asks the caller to persist it.
 */
const text = defineModel<string>({ required: true })

const props = defineProps<{ title: string; saving?: boolean }>()

const emit = defineEmits<{ save: []; close: [] }>()

// The card dialog underneath also listens for Escape on the document: handle it
// here and stop it, so Escape closes only this editor.
function onEscape(): void {
    emit('close')
}
</script>

<template>
    <Dialog :visible="true" modal :close-on-escape="false" :dismissable-mask="false"
            :header="props.title"
            :style="{ width: '90rem', maxWidth: 'calc(100vw - 2rem)' }"
            @update:visible="(value: boolean) => { if (!value) emit('close') }">
        <div @keydown.esc.stop.prevent="onEscape">
            <div class="grid grid-cols-2 gap-3" style="height: 70vh">
                <MdEditor v-model="text" height="70vh"/>
                <div :class="['overflow-auto rounded-lg bg-raised/50 px-3 py-2', MARKDOWN_CLASS]"
                     v-html="renderMarkdown(text)"/>
            </div>
            <div class="mt-2 flex justify-end">
                <Button text size="small" title="Save" aria-label="Save"
                        :disabled="props.saving" @click="emit('save')">
                    <i class="fa-solid fa-floppy-disk fa-lg"></i>
                </Button>
            </div>
        </div>
    </Dialog>
</template>
