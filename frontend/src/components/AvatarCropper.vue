<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'

// Square avatar cropper without dependencies: drag with mouse or finger, zoom with the
// wheel, a two-finger pinch or the slider. Emits a lossless 512×512 PNG; the server
// re-encodes it once to WebP (MediaPolicy::AVATAR_SIZE / WEBP_QUALITY).

const props = defineProps<{ file: File }>()
const emit = defineEmits<{ cancel: []; done: [file: File] }>()

const OUTPUT = 512
const VIEW = 288 // on-screen size of the square, CSS px
const MAX_ZOOM = 4 // relative to the smallest zoom that still covers the square

const canvasEl = ref<HTMLCanvasElement | null>(null)
const image = ref<HTMLImageElement | null>(null)
const loadError = ref<string | null>(null)
const saving = ref(false)

// The image top-left corner relative to the square, in CSS px, and its scale.
const scale = ref(1)
const offsetX = ref(0)
const offsetY = ref(0)

const minScale = computed(() => {
    const img = image.value
    return img === null ? 1 : VIEW / Math.min(img.naturalWidth, img.naturalHeight)
})

// Slider value: 1 = cover, MAX_ZOOM = closest.
const zoom = computed({
    get: () => scale.value / minScale.value,
    set: (value: number) => zoomAt(value * minScale.value, VIEW / 2, VIEW / 2),
})

let objectUrl: string | null = null

onMounted(() => {
    objectUrl = URL.createObjectURL(props.file)
    const img = new Image()
    img.onload = () => {
        image.value = img
        scale.value = minScale.value
        // centred, like the server's default crop
        offsetX.value = (VIEW - img.naturalWidth * scale.value) / 2
        offsetY.value = (VIEW - img.naturalHeight * scale.value) / 2
        draw()
    }
    img.onerror = () => {
        loadError.value = 'This image could not be opened'
    }
    // browsers apply the EXIF orientation when decoding, so the crop matches what is seen
    img.src = objectUrl
})

onBeforeUnmount(() => {
    if (objectUrl !== null) URL.revokeObjectURL(objectUrl)
})

watch([scale, offsetX, offsetY], () => draw())

function clamp(): void {
    const img = image.value
    if (img === null) return
    const width = img.naturalWidth * scale.value
    const height = img.naturalHeight * scale.value
    // the image must always cover the whole square
    offsetX.value = Math.min(0, Math.max(VIEW - width, offsetX.value))
    offsetY.value = Math.min(0, Math.max(VIEW - height, offsetY.value))
}

/** Zoom keeping the point (x, y) of the square under the cursor or fingers. */
function zoomAt(nextScale: number, x: number, y: number): void {
    const bounded = Math.min(minScale.value * MAX_ZOOM, Math.max(minScale.value, nextScale))
    const ratio = bounded / scale.value
    offsetX.value = x - (x - offsetX.value) * ratio
    offsetY.value = y - (y - offsetY.value) * ratio
    scale.value = bounded
    clamp()
}

function draw(): void {
    const canvas = canvasEl.value
    const img = image.value
    if (canvas === null || img === null) return

    const dpr = window.devicePixelRatio || 1
    canvas.width = VIEW * dpr
    canvas.height = VIEW * dpr
    const ctx = canvas.getContext('2d')
    if (ctx === null) return

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.clearRect(0, 0, VIEW, VIEW)
    ctx.drawImage(img, offsetX.value, offsetY.value, img.naturalWidth * scale.value, img.naturalHeight * scale.value)

    // dim everything outside the circle the avatar is shown in
    ctx.fillStyle = 'rgba(0, 0, 0, 0.55)'
    ctx.beginPath()
    ctx.rect(0, 0, VIEW, VIEW)
    ctx.arc(VIEW / 2, VIEW / 2, VIEW / 2, 0, Math.PI * 2, true)
    ctx.fill()
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)'
    ctx.lineWidth = 1.5
    ctx.beginPath()
    ctx.arc(VIEW / 2, VIEW / 2, VIEW / 2 - 0.75, 0, Math.PI * 2)
    ctx.stroke()
}

// --- pointer input: one pointer pans, two pointers pinch-zoom ---

const pointers = new Map<number, { x: number; y: number }>()
let pinchDistance = 0

function localPoint(event: PointerEvent | WheelEvent): { x: number; y: number } {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
    return { x: event.clientX - rect.left, y: event.clientY - rect.top }
}

function distance(): number {
    const [a, b] = [...pointers.values()]
    return Math.hypot(a.x - b.x, a.y - b.y)
}

function onPointerDown(event: PointerEvent): void {
    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
    pointers.set(event.pointerId, localPoint(event))
    if (pointers.size === 2) pinchDistance = distance()
}

function onPointerMove(event: PointerEvent): void {
    const previous = pointers.get(event.pointerId)
    if (previous === undefined) return
    const point = localPoint(event)
    pointers.set(event.pointerId, point)

    if (pointers.size === 1) {
        offsetX.value += point.x - previous.x
        offsetY.value += point.y - previous.y
        clamp()
        return
    }
    if (pointers.size === 2 && pinchDistance > 0) {
        const [a, b] = [...pointers.values()]
        const next = distance()
        zoomAt(scale.value * (next / pinchDistance), (a.x + b.x) / 2, (a.y + b.y) / 2)
        pinchDistance = next
    }
}

function onPointerUp(event: PointerEvent): void {
    pointers.delete(event.pointerId)
    pinchDistance = pointers.size === 2 ? distance() : 0
}

function onWheel(event: WheelEvent): void {
    const point = localPoint(event)
    zoomAt(scale.value * (event.deltaY < 0 ? 1.1 : 1 / 1.1), point.x, point.y)
}

// --- output ---

async function save(): Promise<void> {
    const img = image.value
    if (img === null) return
    saving.value = true
    try {
        const out = document.createElement('canvas')
        out.width = OUTPUT
        out.height = OUTPUT
        const ctx = out.getContext('2d')
        if (ctx === null) throw new Error('Canvas is not available')
        ctx.imageSmoothingQuality = 'high'
        // the square in image pixels
        const side = VIEW / scale.value
        ctx.drawImage(img, -offsetX.value / scale.value, -offsetY.value / scale.value, side, side, 0, 0, OUTPUT, OUTPUT)

        const blob = await new Promise<Blob | null>((resolve) => out.toBlob(resolve, 'image/png'))
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
            :style="{ width: '22rem', maxWidth: 'calc(100vw - 2rem)' }">
        <div class="flex flex-col items-center gap-4">
            <p v-if="loadError" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text">
                {{ loadError }}
            </p>
            <canvas
                v-show="image !== null"
                ref="canvasEl"
                class="cursor-grab touch-none rounded-lg bg-page active:cursor-grabbing"
                :style="{ width: `${VIEW}px`, height: `${VIEW}px` }"
                aria-label="Drag to move the photo, scroll or pinch to zoom"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @wheel.prevent="onWheel"/>
            <p v-if="image === null && loadError === null" class="py-24 text-sm text-faint">Loading…</p>
            <label class="flex w-full items-center gap-3 text-sm text-muted">
                Zoom
                <input
                    v-model.number="zoom"
                    type="range"
                    min="1"
                    :max="MAX_ZOOM"
                    step="0.01"
                    class="flex-1 accent-brand"
                    :disabled="image === null"/>
            </label>
            <p class="text-xs text-faint">Drag to move, scroll or pinch to zoom.</p>
        </div>
        <template #footer>
            <Button severity="secondary" label="Cancel" @click="emit('cancel')"/>
            <Button label="Save" :disabled="image === null || saving" @click="save"/>
        </template>
    </Dialog>
</template>
