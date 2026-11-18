<script setup lang="ts">
import { computed } from 'vue'
import { useCallStore } from './store'

const props = defineProps<{ projectId: string; userId?: string }>()

const call = useCallStore()
const count = computed(() => call.participantsOf(props.projectId).length)
const visible = computed(() =>
    props.userId === undefined ? count.value > 0 : call.isInRoom(props.projectId, props.userId),
)
</script>

<template>
    <span
        v-if="visible"
        class="inline-flex items-center gap-1 rounded-full bg-brand/15 px-1.5 py-0.5 text-[10px] font-medium text-brand-text"
        :title="userId === undefined ? `${count} in call` : 'In call'"
    >
        <span class="h-1.5 w-1.5 rounded-full bg-brand"/>
        <template v-if="userId === undefined">{{ count }}</template>
    </span>
</template>
