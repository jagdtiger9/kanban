<script setup lang="ts">
import { computed } from 'vue'
import { avatarColor, initials } from '../utils/people'

const props = withDefaults(
    defineProps<{
        userId: string
        name: string | null
        size?: 'sm' | 'md'
    }>(),
    { size: 'sm' },
)

const sizeClass = computed(() => (props.size === 'md' ? 'h-9 w-9 text-xs' : 'h-6 w-6 text-[10px]'))
const label = computed(() => initials(props.name))
const color = computed(() => avatarColor(props.userId))
</script>

<template>
    <span
        aria-hidden="true"
        class="flex shrink-0 items-center justify-center rounded-full font-semibold text-white"
        :class="sizeClass"
        :style="{ backgroundColor: color }"
    >
        {{ label }}
    </span>
</template>
