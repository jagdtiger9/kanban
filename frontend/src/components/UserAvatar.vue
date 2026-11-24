<script setup lang="ts">
import { computed } from 'vue'
import { avatarUrl } from '../api/users'
import { avatarColor, initials } from '../utils/people'

const props = withDefaults(
    defineProps<{
        userId: string
        name: string | null
        avatarVersion?: string | null
        color?: string | null
        size?: 'sm' | 'md' | 'lg' | 'card' | 'card-sm',
        title?: string | null
    }>(),
    { avatarVersion: null, color: null, size: 'sm' },
)

const sizeClass = computed(() => {
    switch (props.size) {
        case 'lg':
            return 'h-24 w-24 text-2xl'
        case 'md':
            return 'h-9 w-9 text-xs'
        case 'card':
            return 'h-7 w-7 text-xs'
        case 'card-sm':
            return 'h-5 w-5 text-xs'
        default:
            return 'h-6 w-6 text-[10px]'
    }
})
const label = computed(() => initials(props.name))
const color = computed(() => props.color ?? avatarColor(props.userId))
const src = computed(() => avatarUrl(props.userId, props.avatarVersion))
</script>

<template>
    <span
        aria-hidden="true"
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-full font-semibold text-white"
        :class="sizeClass"
        :style="src === null ? { backgroundColor: color } : undefined">
        <img v-if="src !== null"
             :src="src" :alt="props.title || ''" :title="props.title || ''"
             class="h-full w-full object-cover"/>
        <template v-else>{{ label }}</template>
    </span>
</template>
