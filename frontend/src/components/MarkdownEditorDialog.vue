<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import Modal from './Modal.vue'
import MdEditor from './MdEditor.vue'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'

// Full-size markdown editing: the editor on the left, the rendered result on the right.
// Save hands the text to the caller; closing without Save leaves everything as it was.
const props = withDefaults(defineProps<{ text: string, title?: string, saving?: boolean }>(), {
    title: 'Edit',
    saving: false,
})

const emit = defineEmits<{ save: [text: string], close: [] }>()

const draft = ref(props.text)
</script>

<template>
    <Modal size="xl" @close="emit('close')">
        <template #title>{{ props.title }}</template>

        <div class="grid gap-4 lg:grid-cols-2">
            <MdEditor v-model="draft" height="60vh"/>
            <div
                :class="['h-[60vh] overflow-y-auto rounded-lg border border-line px-3 py-2 text-body', MARKDOWN_CLASS]"
                v-html="renderMarkdown(draft)"/>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <Button label="Save" size="small" :disabled="props.saving" @click="emit('save', draft)"/>
            <Button severity="secondary" label="Cancel" size="small" @click="emit('close')"/>
        </div>
    </Modal>
</template>
