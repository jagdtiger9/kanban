<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { ApiError } from '../api/client'
import { renameColumn } from '../api/board'
import Modal from './Modal.vue'

const props = defineProps<{ columnId: string; initialName: string }>()

const emit = defineEmits<{ close: []; renamed: [name: string] }>()

const name = ref(props.initialName)
const error = ref<string | null>(null)
const submitting = ref(false)

async function submit(): Promise<void> {
    error.value = null
    submitting.value = true
    try {
        await renameColumn(props.columnId, name.value)
        emit('renamed', name.value)
        emit('close')
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Something went wrong'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <Modal @close="emit('close')">
        <template #title>Rename column</template>
        <form class="space-y-4" @submit.prevent="submit">
            <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text">
                {{ error }}
            </p>
            <div>
                <label for="rename-column-name" class="mb-1 block text-sm font-medium text-body">Name</label>
                <InputText id="rename-column-name" v-model="name" autofocus class="w-full"/>
            </div>
            <div class="flex justify-end gap-2">
                <Button severity="secondary" label="Cancel" @click="emit('close')"/>
                <Button type="submit" label="Rename" :disabled="submitting || name === ''"/>
            </div>
        </form>
    </Modal>
</template>
