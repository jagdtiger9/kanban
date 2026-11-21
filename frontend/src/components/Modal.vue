<script setup lang="ts">
import { computed } from 'vue'
import Dialog from 'primevue/dialog'

const props = withDefaults(defineProps<{ size?: 'md' | 'lg' | 'xl' }>(), { size: 'md' })

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
            width: props.size === 'xl' ? '80rem' : props.size === 'lg' ? '64rem' : '56rem',
            maxWidth: 'calc(100vw - 2rem)',
        }">
        <template #header>
            <div class="flex w-full justify-between mr-5">
                <div class="p-dialog-title min-w-0 flex-1">
                    <slot name="title"/>
                </div>
                <div>
                    <slot name="attributes"/>
                </div>
            </div>
        </template>
        <slot/>
    </Dialog>
</template>
