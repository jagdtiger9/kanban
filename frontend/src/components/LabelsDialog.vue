<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import * as boardApi from '../api/board'
import type { LabelPayload } from '../api/board'
import { ApiError } from '../api/client'
import ConfirmDialog from './ConfirmDialog.vue'
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

const newName = ref('')
const newColor = ref('#3b82f6')
const creating = ref(false)

const editingId = ref<string | null>(null)
const editName = ref('')
const editColor = ref('#3b82f6')
const savingEdit = ref(false)

const deleteTarget = ref<LabelPayload | null>(null)
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

async function confirmDelete(): Promise<void> {
    const target = deleteTarget.value
    if (!target || deleting.value) return
    deleting.value = true
    error.value = null
    try {
        await boardApi.deleteLabel(target.id)
        deleteTarget.value = null
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
                        <input
                            v-model="editColor"
                            type="color"
                            class="h-8 w-8 shrink-0 cursor-pointer rounded border border-line-strong bg-raised"
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
                        <button
                            type="button"
                            class="cursor-pointer text-xs text-muted transition-colors hover:text-strong"
                            @click="startEdit(label)"
                        >
                            Rename
                        </button>
                        <button
                            v-if="props.canDelete"
                            type="button"
                            class="cursor-pointer text-xs text-danger-text transition-colors hover:text-danger-text-hover"
                            @click="deleteTarget = label"
                        >
                            Delete
                        </button>
                    </template>
                </li>
            </ul>
            <p v-else class="text-sm text-faint">No labels yet.</p>

            <div class="border-t border-line pt-3">
                <p class="mb-2 text-sm font-medium text-body">New label</p>
                <div class="flex items-center gap-2">
                    <input
                        v-model="newColor"
                        type="color"
                        class="h-9 w-9 shrink-0 cursor-pointer rounded border border-line-strong bg-raised"
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

        <ConfirmDialog
            v-if="deleteTarget"
            title="Delete label"
            :message="`Delete label “${deleteTarget.name}”? It will be removed from all cards.`"
            confirm-label="Delete"
            danger
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </Modal>
</template>
