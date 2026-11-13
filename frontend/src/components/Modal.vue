<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

const props = withDefaults(defineProps<{ size?: 'md' | 'lg' }>(), { size: 'md' })

const emit = defineEmits<{ close: [] }>()

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close')
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" @click="emit('close')"/>
            <div
                class="relative max-h-[85vh] w-full overflow-y-auto rounded-2xl border border-line-strong bg-panel p-6 shadow-2xl"
                :class="props.size === 'lg' ? 'max-w-lg' : 'max-w-md'"
            >
                <div v-if="$slots.title" class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-strong">
                        <slot name="title"/>
                    </h3>
                    <button
                        type="button"
                        aria-label="Close"
                        class="cursor-pointer rounded-lg p-1 text-muted transition-colors hover:bg-raised hover:text-strong"
                        @click="emit('close')"
                    >
                        ✕
                    </button>
                </div>
                <slot/>
            </div>
        </div>
    </Teleport>
</template>
