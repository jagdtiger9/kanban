<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { CircleStencil, Cropper, type CropperResult } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

// Square avatar cropper on vue-advanced-cropper: a fixed circle in the middle (the shape
// the avatar is shown in); the photo is dragged and zoomed under it with the mouse,
// the wheel or a two-finger pinch. Emits a lossless 512×512 PNG; the server re-encodes
// it once to WebP (MediaPolicy::AVATAR_SIZE / WEBP_QUALITY).

const props = defineProps<{ file: File }>()
const emit = defineEmits<{ cancel: []; done: [file: File] }>()

const OUTPUT = 512
const STENCIL_MARGIN = 24 // free space around the circle inside the crop area, CSS px

const cropper = ref<{ getResult: () => CropperResult } | null>(null)
const ready = ref(false)
const loadError = ref<string | null>(null)
const saving = ref(false)

// The browser applies the EXIF orientation when decoding, so the crop matches what is seen.
const src = URL.createObjectURL(props.file)
onBeforeUnmount(() => URL.revokeObjectURL(src))

/** The circle stays put and takes the crop area minus a margin. */
function stencilSize({ boundaries }: { boundaries: { width: number; height: number } }): { width: number; height: number } {
    const side = Math.min(boundaries.width, boundaries.height) - STENCIL_MARGIN * 2
    return { width: side, height: side }
}

function onError(): void {
    loadError.value = 'This image could not be opened'
}

async function save(): Promise<void> {
    const canvas = cropper.value?.getResult().canvas
    if (canvas === undefined) return
    saving.value = true
    try {
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png'))
        if (blob === null) throw new Error('The image could not be prepared')
        emit('done', new File([blob], 'avatar.png', { type: 'image/png' }))
    } catch (e) {
        loadError.value = e instanceof Error ? e.message : 'The image could not be prepared'
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
    <Dialog v-model:visible="visible" modal dismissable-mask close-on-escape header="Crop photo"
            :style="{ width: '24rem', maxWidth: 'calc(100vw - 2rem)' }">
        <div class="flex flex-col items-center gap-3">
            <p v-if="loadError" class="w-full rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text">
                {{ loadError }}
            </p>
            <!-- Fixed stencil: the circle does not move or resize, the image does. -->
            <Cropper
                ref="cropper"
                class="h-80 w-full overflow-hidden rounded-lg"
                :src="src"
                :stencil-component="CircleStencil"
                :stencil-props="{ handlers: {}, movable: false, resizable: false, aspectRatio: 1 }"
                :stencil-size="stencilSize"
                :resize-image="{ adjustStencil: false }"
                image-restriction="stencil"
                :canvas="{ width: OUTPUT, height: OUTPUT }"
                @ready="ready = true"
                @error="onError"/>
            <p class="text-xs text-faint">Drag to move, scroll or pinch to zoom.</p>
        </div>
        <template #footer>
            <Button severity="secondary" label="Cancel" @click="emit('cancel')"/>
            <Button label="Save" :disabled="!ready || saving" @click="save"/>
        </template>
    </Dialog>
</template>
