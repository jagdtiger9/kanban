<script setup lang="ts">
import Modal from './Modal.vue'

withDefaults(
    defineProps<{
        title: string
        message: string
        confirmLabel?: string
        danger?: boolean
    }>(),
    {
        confirmLabel: 'Confirm',
        danger: false,
    },
)

const emit = defineEmits<{ confirm: []; cancel: [] }>()
</script>

<template>
    <Modal @close="emit('cancel')">
        <template #title>{{ title }}</template>
        <p class="text-sm text-body">{{ message }}</p>
        <div class="mt-6 flex justify-end gap-2">
            <button
                type="button"
                class="cursor-pointer rounded-lg border border-line-strong px-4 py-2 text-sm text-body transition-colors hover:border-line-emphasis hover:text-strong"
                @click="emit('cancel')"
            >
                Cancel
            </button>
            <button
                type="button"
                class="cursor-pointer rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors"
                :class="danger ? 'bg-danger hover:bg-danger-hover' : 'bg-brand hover:bg-brand-hover'"
                @click="emit('confirm')"
            >
                {{ confirmLabel }}
            </button>
        </div>
    </Modal>
</template>
