<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { CircleStencil, Cropper, type CropperResult } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

// Square avatar cropper on vue-advanced-cropper: a fixed circle in the middle (the shape
// the avatar is shown in); the photo is dragged and zoomed under it with the mouse,
// the wheel, a two-finger pinch or the zoom slider. Emits a lossless 512×512 PNG; the server re-encodes
// it once to WebP (MediaPolicy::AVATAR_SIZE / WEBP_QUALITY).

const props = defineProps<{ file: File }>()
const emit = defineEmits<{ cancel: []; done: [file: File] }>()

const OUTPUT = 512
const STENCIL_MARGIN = 24 // free space around the circle inside the crop area, CSS px
// Zoom is a percentage of the photo's own resolution in the 512 px result:
// 100% = one photo pixel per avatar pixel (a 512 px square is cut out), 200% = a 256 px
// square stretched to 512 (artifacts accepted: any small detail can be picked).
const MAX_PERCENT = 200

const cropper = ref<{ getResult: () => CropperResult; zoom: (factor: number) => void } | null>(null)
const zoomPercent = ref(100)
// Slider range, known once the photo is loaded: from "the whole photo covers the circle"
// to MAX_PERCENT (or the start, for a photo smaller than OUTPUT * 100 / MAX_PERCENT).
const minPercent = ref(1)
const maxPercent = ref(MAX_PERCENT)
// Smallest crop square (image px) = closest zoom, so the wheel and pinch stop at the
// same limit as the slider.
const minCropSide = ref<number | undefined>(undefined)
// While the slider is being dragged it is the source of truth: the cropper's own
// (rounded) result must not pull it back.
let sliderActive = false
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

/** Side of the cropped square in image pixels, and the largest one (zoom 1). */
function cropSides(result: CropperResult): { side: number; maxSide: number } {
    return {
        side: result.coordinates.width,
        maxSide: Math.min(result.image.width, result.image.height),
    }
}

/** Crop square side (image px) <-> zoom percentage of the 512 px result. */
const toPercent = (side: number): number => (OUTPUT / side) * 100
const toSide = (percent: number): number => (OUTPUT * 100) / percent

/** Wheel, pinch or drag changed the crop: keep the slider in step. */
function onChange(result: CropperResult): void {
    if (sliderActive) return
    const { side } = cropSides(result)
    if (side > 0) zoomPercent.value = Math.min(maxPercent.value, Math.max(minPercent.value, toPercent(side)))
}

/** Slider moved: zoom the photo so the crop matches the chosen percentage. */
function onSlider(value: number): void {
    sliderActive = true
    zoomPercent.value = value
    const instance = cropper.value
    if (instance === null) return
    const { side } = cropSides(instance.getResult())
    const target = toSide(value)
    if (side > 0 && target > 0) instance.zoom(side / target)
}

/** Photo loaded: set the slider range and the same limit for the wheel and pinch. */
function onReady(): void {
    ready.value = true
    const result = cropper.value?.getResult()
    if (result === undefined) return
    const { side, maxSide } = cropSides(result)
    const smallest = Math.min(maxSide, toSide(MAX_PERCENT))
    minCropSide.value = smallest
    minPercent.value = toPercent(maxSide)
    maxPercent.value = toPercent(smallest)
    if (side > 0) zoomPercent.value = toPercent(side)
}

/** Slider released: the cropper's result may drive it again (wheel, pinch). */
function endSlider(): void {
    sliderActive = false
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
                :transitions="false"
                :debounce="false"
                :canvas="{ width: OUTPUT, height: OUTPUT }"
                :min-width="minCropSide"
                :min-height="minCropSide"
                :wheel-resize="{ ratio: 0.25 }"
                @ready="onReady"
                @change="onChange"
                @error="onError"/>
            <label class="flex w-full items-center gap-3 text-sm text-muted">
                Zoom
                <input
                    :value="zoomPercent"
                    type="range"
                    :min="minPercent"
                    :max="maxPercent"
                    step="0.1"
                    class="flex-1 accent-brand"
                    :disabled="!ready"
                    @input="onSlider(Number(($event.target as HTMLInputElement).value))"
                    @change="endSlider"/>
                <span class="w-12 text-right tabular-nums text-body">{{ Math.round(zoomPercent) }}%</span>
            </label>
            <p class="text-xs text-faint">
                Drag to move, scroll, pinch or use the slider to zoom. 100% = full detail of the photo
                in the 512 px avatar; above 100% the cut-out is stretched.
            </p>
        </div>
        <template #footer>
            <Button severity="secondary" label="Cancel" @click="emit('cancel')"/>
            <Button label="Save" :disabled="!ready || saving" @click="save"/>
        </template>
    </Dialog>
</template>
