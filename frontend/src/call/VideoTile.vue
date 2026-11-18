<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue'

const props = defineProps<{
    name: string
    stream: MediaStream | null
    showVideo: boolean
    muted?: boolean
    mirrored?: boolean
    micOff?: boolean
    status?: 'connecting' | 'connected' | 'failed'
}>()

const videoEl = ref<HTMLVideoElement | null>(null)
const audioEl = ref<HTMLAudioElement | null>(null)

// Remote audio plays through its own element, so it keeps playing while the video is hidden.
watchEffect(() => {
    if (videoEl.value !== null && videoEl.value.srcObject !== props.stream) videoEl.value.srcObject = props.stream
    if (audioEl.value !== null && audioEl.value.srcObject !== props.stream) audioEl.value.srcObject = props.stream
})

const initial = computed(() => (props.name[0] ?? '?').toUpperCase())
const statusText = computed(() => {
    if (props.status === 'connecting') return 'Connecting…'
    if (props.status === 'failed') return `Can't connect to ${props.name}`
    return null
})
</script>

<template>
    <div class="relative flex aspect-video items-center justify-center overflow-hidden rounded-lg bg-page">
        <video
            v-show="showVideo"
            ref="videoEl"
            autoplay
            playsinline
            muted
            class="h-full w-full object-contain"
            :class="{ '-scale-x-100': mirrored }"
        />
        <audio v-if="!muted" ref="audioEl" autoplay/>
        <div
            v-if="!showVideo"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-brand/20 text-xl font-semibold text-brand-text"
        >
            {{ initial }}
        </div>
        <div class="absolute inset-x-0 bottom-0 flex items-center gap-1 bg-black/50 px-2 py-1 text-xs text-white">
            <span class="truncate">{{ name }}</span>
            <span v-if="micOff" title="Microphone off">🔇</span>
        </div>
        <div
            v-if="statusText"
            class="absolute inset-x-0 top-0 px-2 py-1 text-center text-xs"
            :class="status === 'failed' ? 'bg-danger/80 text-white' : 'bg-black/40 text-white'"
        >
            {{ statusText }}
        </div>
    </div>
</template>
