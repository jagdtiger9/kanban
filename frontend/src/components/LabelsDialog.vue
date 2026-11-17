<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import ColorPicker from 'primevue/colorpicker'
import InputText from 'primevue/inputtext'
import { useConfirm } from 'primevue/useconfirm'
import * as boardApi from '../api/board'
import type { LabelPayload } from '../api/board'
import { ApiError } from '../api/client'
import Modal from './Modal.vue'

const props = defineProps<{
    projectId: string
    labels: LabelPayload[]
    canDelete: boolean
}>()

const emit = defineEmits<{
    close: []
    changed: []
}>()

const error = ref<string | null>(null)
const confirm = useConfirm()

const newName = ref('')
const newColor = ref('#3b82f6')
const creating = ref(false)

const editingId = ref<string | null>(null)
const editName = ref('')
const editColor = ref('#3b82f6')
const savingEdit = ref(false)

const deleting = ref(false)

async function create(): Promise<void> {
    const name = newName.value.trim()
    if (name === '') return
    creating.value = true
    error.value = null
    try {
        await boardApi.createLabel(props.projectId, name, newColor.value)
        newName.value = ''
        emit('changed')
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to create label'
    } finally {
        creating.value = false
    }
}

function startEdit(label: LabelPayload): void {
    editingId.value = label.id
    editName.value = label.name
    editColor.value = label.color
}

async function saveEdit(): Promise<void> {
    const labelId = editingId.value
    const name = editName.value.trim()
    if (!labelId || name === '') return
    savingEdit.value = true
    error.value = null
    try {
        await boardApi.updateLabel(labelId, name, editColor.value)
        editingId.value = null
        emit('changed')
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to update label'
    } finally {
        savingEdit.value = false
    }
}

function askDelete(label: LabelPayload): void {
    confirm.require({
        header: 'Delete label',
        message: `Delete label “${label.name}”? It will be removed from all cards.`,
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () => confirmDelete(label),
    })
}

async function confirmDelete(target: LabelPayload): Promise<void> {
    if (deleting.value) return
    deleting.value = true
    error.value = null
    try {
        await boardApi.deleteLabel(target.id)
        emit('changed')
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to delete label'
    } finally {
        deleting.value = false
    }
}
</script>

<template>
    <Modal @close="emit('close')">
        <template #title>Labels</template>
        <div class="space-y-4">
            <p
                v-if="error"
                class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text"
            >
                {{ error }}
            </p>

            <ul v-if="props.labels.length > 0" class="space-y-1">
                <li
                    v-for="label in props.labels"
                    :key="label.id"
                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-raised"
                >
                    <span class="h-3.5 w-3.5 shrink-0 rounded-full" :style="{ backgroundColor: label.color }"/>
                    <template v-if="editingId === label.id">
                        <InputText
                            v-model="editName"
                            size="small"
                            class="min-w-0 flex-1"
                            autofocus
                            @keydown.enter="saveEdit"
                            @keydown.esc="editingId = null"
                        />
                        <ColorPicker
                            :model-value="editColor"
                            format="hex"
                            @update:model-value="(value: string) => (editColor = `#${value}`)"
                        />
                        <Button
                            label="Save"
                            size="small"
                            :disabled="savingEdit || editName.trim() === ''"
                            @click="saveEdit"
                        />
                    </template>
                    <template v-else>
                        <span class="min-w-0 flex-1 truncate text-sm text-strong">{{ label.name }}</span>
                        <Button
                            severity="secondary"
                            text
                            size="small"
                            label="Rename"
                            @click="startEdit(label)"
                        />
                        <Button
                            v-if="props.canDelete"
                            severity="danger"
                            text
                            size="small"
                            label="Delete"
                            @click="askDelete(label)"
                        />
                    </template>
                </li>
            </ul>
            <p v-else class="text-sm text-faint">No labels yet.</p>

            <div class="border-t border-line pt-3">
                <p class="mb-2 text-sm font-medium text-body">New label</p>
                <div class="flex items-center gap-2">
                    <ColorPicker
                        :model-value="newColor"
                        format="hex"
                        @update:model-value="(value: string) => (newColor = `#${value}`)"
                    />
                    <InputText
                        v-model="newName"
                        placeholder="Label name"
                        class="min-w-0 flex-1"
                        @keydown.enter="create"
                    />
                    <Button label="Add" :disabled="creating || newName.trim() === ''" @click="create"/>
                </div>
            </div>
        </div>
    </Modal>
</template>
