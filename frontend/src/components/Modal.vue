<script setup lang="ts">
import { computed } from 'vue'
import Dialog from 'primevue/dialog'

const props = withDefaults(defineProps<{ size?: 'md' | 'lg' }>(), { size: 'md' })

const emit = defineEmits<{ close: [] }>()

const visible = computed({
    get: () => true,
    set: (value: boolean) => {
        if (!value) emit('close')
    },
})
</script>

<template>
    <Dialog v-model:visible="visible" modal dismissable-mask close-on-escape
            :style="{
            width: props.size === 'lg' ? '64rem' : '56rem',
            maxWidth: 'calc(100vw - 2rem)',
        }">
        <template #header>
            <span class="p-dialog-title"><slot name="title"/></span>
        </template>
        <slot/>
    </Dialog>
</template>
