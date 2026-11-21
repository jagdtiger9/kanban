<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { isImageMime, pastedImageToWebp } from '../utils/attachments'

// One image from the clipboard. First tries to read it right away (needs HTTPS or
// localhost and the browser's permission); otherwise the user presses Ctrl+V — or
// long-press → Paste on a phone — inside the paste area, which works everywhere.

const emit = defineEmits<{ cancel: []; done: [file: File] }>()

const image = ref<Blob | null>(null)
const previewUrl = ref<string | null>(null)
const status = ref<'reading' | 'waiting' | 'ready'>('reading')
const hint = ref<string | null>(null)
const saving = ref(false)
const pasteArea = ref<HTMLElement | null>(null)

function setImage(blob: Blob): void {
    if (previewUrl.value !== null) URL.revokeObjectURL(previewUrl.value)
    image.value = blob
    previewUrl.value = URL.createObjectURL(blob)
    status.value = 'ready'
    hint.value = null
}

async function waitForPaste(message: string | null): Promise<void> {
    status.value = 'waiting'
    hint.value = message
    await nextTick()
    pasteArea.value?.focus()
}

onMounted(async () => {
    if (!window.isSecureContext || navigator.clipboard?.read === undefined) {
        await waitForPaste(null)
        return
    }
    try {
        for (const item of await navigator.clipboard.read()) {
            const type = item.types.find(isImageMime)
            if (type !== undefined) {
                setImage(await item.getType(type))
                return
            }
        }
        await waitForPaste('There is no image in the clipboard yet.')
    } catch {
        // permission denied or the browser asks the user itself — fall back to Ctrl+V
        await waitForPaste(null)
    }
})

onBeforeUnmount(() => {
    if (previewUrl.value !== null) URL.revokeObjectURL(previewUrl.value)
})

function onPaste(event: ClipboardEvent): void {
    event.preventDefault()
    const file = [...(event.clipboardData?.files ?? [])].find((f) => isImageMime(f.type))
    if (file === undefined) {
        hint.value = 'That is not an image. Copy a picture or a screenshot and paste again.'
        return
    }
    setImage(file)
}

async function save(): Promise<void> {
    if (image.value === null) return
    saving.value = true
    try {
        emit('done', await pastedImageToWebp(image.value))
    } finally {
        saving.value = false
    }
}

const visible = computed({
    get: () => true,
    set: (value: boolean) => {
        if (!value) emit('cancel')
    },
})
</script>

<template>
    <Dialog v-model:visible="visible" modal dismissable-mask close-on-escape header="Paste from clipboard"
            :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
        <div class="space-y-3">
            <p v-if="status === 'reading'" class="py-10 text-center text-sm text-faint">Reading the clipboard…</p>

            <div v-else-if="status === 'ready' && previewUrl !== null" class="flex justify-center rounded-lg bg-page p-2">
                <img :src="previewUrl" alt="Image from the clipboard" class="max-h-72 max-w-full rounded object-contain"/>
            </div>

            <div
                v-if="status !== 'reading'"
                ref="pasteArea"
                contenteditable="true"
                role="textbox"
                aria-label="Paste an image here"
                class="cursor-text rounded-lg border border-dashed border-line-strong px-3 py-4 text-center text-sm text-muted caret-transparent outline-none focus:border-brand"
                @paste="onPaste"
                @beforeinput.prevent>
                {{ status === 'ready' ? 'Press Ctrl+V to replace the image' : 'Press Ctrl+V here (on a phone: long-press → Paste)' }}
            </div>

            <p v-if="hint" class="text-xs text-warn-text">{{ hint }}</p>
            <p class="text-xs text-faint">Saved as WebP to keep screenshots small.</p>
        </div>
        <template #footer>
            <Button severity="secondary" label="Cancel" @click="emit('cancel')"/>
            <Button label="OK" :disabled="image === null || saving" @click="save"/>
        </template>
    </Dialog>
</template>
