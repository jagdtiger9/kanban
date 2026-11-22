<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import { attachmentUrl, getAttachmentText, thumbnailUrl } from '../api/attachments'
import type { AttachmentPayload } from '../api/attachments'
import { ApiError } from '../api/client'
import { canPreviewText, formatBytes, isImageMime } from '../utils/attachments'

const props = defineProps<{
    attachment: AttachmentPayload
    canDelete: boolean
}>()

const emit = defineEmits<{ remove: [] }>()

const isImage = computed(() => isImageMime(props.attachment.mimeType))
const previewable = computed(() => canPreviewText(props.attachment.mimeType, props.attachment.sizeBytes))
const extension = computed(() => {
    const dot = props.attachment.name.lastIndexOf('.')

    return dot === -1 ? 'file' : props.attachment.name.slice(dot + 1, dot + 5).toUpperCase()
})

const previewOpen = ref(false)
const previewText = ref<string | null>(null)
const previewLoading = ref(false)
const previewError = ref<string | null>(null)

async function togglePreview(): Promise<void> {
    previewOpen.value = !previewOpen.value
    if (!previewOpen.value || previewText.value !== null || previewLoading.value) {
        return
    }

    previewLoading.value = true
    previewError.value = null
    try {
        previewText.value = await getAttachmentText(props.attachment.id)
    } catch (e) {
        previewError.value = e instanceof ApiError ? e.message : 'Failed to load preview'
    } finally {
        previewLoading.value = false
    }
}
</script>

<template>
    <div class="rounded-lg border border-line bg-raised/40">
        <div class="flex items-center gap-2 px-2 py-1.5">
            <a
                :href="attachmentUrl(attachment.id)"
                target="_blank"
                rel="noopener noreferrer"
                class="shrink-0"
                tabindex="-1"
            >
                <img
                    v-if="isImage"
                    :src="thumbnailUrl(attachment.id)"
                    :alt="attachment.name"
                    class="h-10 w-10 rounded object-cover"
                />
                <span
                    v-else
                    class="flex h-10 w-10 items-center justify-center rounded bg-active text-[10px] font-medium text-muted uppercase"
                >
                    {{ extension }}
                </span>
            </a>
            <div class="min-w-0 flex-1">
                <a
                    :href="attachmentUrl(attachment.id)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block truncate text-strong hover:underline"
                >
                    {{ attachment.name }}
                </a>
                <p class="text-[11px] text-faint">{{ formatBytes(attachment.sizeBytes) }}</p>
            </div>
            <Button
                v-if="previewable"
                severity="secondary"
                text
                size="small"
                :label="previewOpen ? 'Hide' : 'Preview'"
                @click="togglePreview"
            />
            <Button severity="secondary" class="p-1" text size="small" v-if="canDelete" @click="emit('remove')">
                <i class="fa-solid fa-x"></i>
            </Button>
        </div>
        <div v-if="previewOpen" class="border-t border-line px-3 py-2">
            <p v-if="previewLoading" class="text-xs text-faint">Loading…</p>
            <p v-else-if="previewError" class="text-xs text-danger-text">{{ previewError }}</p>
            <pre v-else class="max-h-60 overflow-auto text-xs whitespace-pre-wrap text-body">{{ previewText }}</pre>
        </div>
    </div>
</template>
