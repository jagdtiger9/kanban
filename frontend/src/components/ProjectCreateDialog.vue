<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { ApiError } from '../api/client'
import { useProjectsStore } from '../stores/projects'
import Modal from './Modal.vue'

const emit = defineEmits<{ close: []; created: [id: string] }>()

const projects = useProjectsStore()

const name = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

async function submit(): Promise<void> {
    error.value = null
    submitting.value = true
    try {
        const project = await projects.createProject(name.value)
        emit('created', project.id)
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
        <template #title>New project</template>
        <form class="space-y-4" @submit.prevent="submit">
            <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                {{ error }}
            </p>
            <div>
                <label for="project-name" class="mb-1 block font-medium text-body">Name</label>
                <InputText id="project-name" v-model="name" autofocus placeholder="e.g. Website redesign" class="w-full"/>
            </div>
            <div class="flex justify-end gap-2">
                <Button severity="secondary" label="Cancel" @click="emit('close')"/>
                <Button type="submit" label="Create" :disabled="submitting"/>
            </div>
        </form>
    </Modal>
</template>
