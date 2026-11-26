<script setup lang="ts">
import type { BoardCardPayload } from '../api/board'

const props = defineProps<{
    card: BoardCardPayload
    canToggle: boolean
}>()

const emit = defineEmits<{
    toggle: []
    open: []
}>()

function onTitleClick(): void {
    if (props.canToggle) {
        emit('toggle')
    } else {
        emit('open')
    }
}
</script>

<template>
    <div
        class="board-card group flex w-full shrink-0 items-center gap-2 rounded-lg border border-line bg-raised p-3 shadow-card transition-colors hover:border-line-emphasis"
        :class="{ 'opacity-50': card.archived }"
        :data-card-id="card.id">
        <button
            v-if="canToggle"
            type="button"
            class="sticker-check shrink-0 cursor-pointer rounded-sm focus-visible:outline-2 focus-visible:outline-brand"
            :class="card.completedAt === null ? 'text-muted hover:text-strong' : 'text-brand-text'"
            :title="card.completedAt === null ? 'Mark done' : 'Mark not done'"
            :aria-label="card.completedAt === null ? 'Mark done' : 'Mark not done'"
            @click.stop="emit('toggle')">
            <i :class="card.completedAt === null ? 'fa-regular fa-square-check' : 'fa-solid fa-square-check'" aria-hidden="true"/>
        </button>
        <span
            v-else
            class="sticker-check shrink-0"
            :class="card.completedAt === null ? 'text-muted' : 'text-brand-text'">
            <i :class="card.completedAt === null ? 'fa-regular fa-square-check' : 'fa-solid fa-square-check'" aria-hidden="true"/>
        </span>
        <button
            type="button"
            class="min-w-0 flex-1 cursor-pointer truncate rounded-sm text-left break-words focus-visible:outline-2 focus-visible:outline-brand"
            :class="{ 'line-through text-muted': card.completedAt !== null }"
            :title="canToggle ? 'Toggle done' : 'Open card'"
            @click.stop="onTitleClick">
            {{ card.title }}
        </button>
        <button
            type="button"
            class="sticker-edit shrink-0 cursor-pointer rounded-sm text-muted opacity-100 hover:text-strong focus-visible:outline-2 focus-visible:outline-brand pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100 pointer-fine:group-focus-within:opacity-100"
            title="Open card"
            aria-label="Open card"
            @click.stop="emit('open')">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"/>
        </button>
    </div>
</template>
