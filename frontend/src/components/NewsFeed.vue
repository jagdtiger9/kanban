<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { ApiError } from '../api/client'
import { createNews, deleteNews, listNews, updateNews } from '../api/news'
import type { NewsPostPayload } from '../api/news'
import { useAuthStore } from '../stores/auth'
import { canPostNews, isGlobalAdmin } from '../utils/roles'
import { MARKDOWN_CLASS, renderMarkdown } from '../utils/markdown'
import UserAvatar from './UserAvatar.vue'

const MdEditor = defineAsyncComponent(() => import('./MdEditor.vue'))

const PER_PAGE = 20

const auth = useAuthStore()
const confirm = useConfirm()

const posts = ref<NewsPostPayload[]>([])
const page = ref(1)
const loading = ref(false)
const loadedAll = ref(false)
const error = ref<string | null>(null)
const composer = ref('')
const posting = ref(false)
const editingId = ref<string | null>(null)
const editBody = ref('')
const savingId = ref<string | null>(null)

const canPost = computed(() => canPostNews(auth.user))

function canModify(post: NewsPostPayload): boolean {
    return auth.user?.id === post.authorId || isGlobalAdmin(auth.user)
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleString()
}

async function loadFirstPage(): Promise<void> {
    loading.value = true
    error.value = null
    try {
        const batch = await listNews(1, PER_PAGE)
        posts.value = batch
        loadedAll.value = batch.length < PER_PAGE
        page.value = 2
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to load the feed'
    } finally {
        loading.value = false
    }
}

async function loadMore(): Promise<void> {
    if (loading.value || loadedAll.value) return
    loading.value = true
    error.value = null
    try {
        const batch = await listNews(page.value, PER_PAGE)
        posts.value.push(...batch)
        if (batch.length < PER_PAGE) {
            loadedAll.value = true
        } else {
            page.value++
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to load more posts'
    } finally {
        loading.value = false
    }
}

function retry(): void {
    if (posts.value.length === 0) {
        void loadFirstPage()
    } else {
        void loadMore()
    }
}

let scrollTimer: ReturnType<typeof setTimeout> | null = null

function onScrollEnd(event: Event): void {
    const target = event.target as HTMLElement
    if (target.clientHeight + Math.abs(target.scrollTop) + 80 < target.scrollHeight) return
    if (scrollTimer !== null) clearTimeout(scrollTimer)
    scrollTimer = setTimeout(() => {
        scrollTimer = null
        void loadMore()
    }, 300)
}

onMounted(loadFirstPage)

onBeforeUnmount(() => {
    if (scrollTimer !== null) clearTimeout(scrollTimer)
})

async function submitPost(): Promise<void> {
    const body = composer.value.trim()
    if (body === '' || posting.value) return
    posting.value = true
    error.value = null
    try {
        const post = await createNews(body)
        posts.value.unshift(post)
        composer.value = ''
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to publish the post'
    } finally {
        posting.value = false
    }
}

function startEdit(post: NewsPostPayload): void {
    editingId.value = post.id
    editBody.value = post.body
}

function cancelEdit(): void {
    editingId.value = null
    editBody.value = ''
}

async function saveEdit(post: NewsPostPayload): Promise<void> {
    const body = editBody.value.trim()
    if (body === '' || savingId.value !== null) return
    savingId.value = post.id
    error.value = null
    try {
        const updated = await updateNews(post.id, body)
        const index = posts.value.findIndex((p) => p.id === updated.id)
        if (index >= 0) {
            posts.value[index] = updated
        }
        cancelEdit()
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to update the post'
    } finally {
        savingId.value = null
    }
}

async function removePost(post: NewsPostPayload): Promise<void> {
    savingId.value = post.id
    error.value = null
    try {
        await deleteNews(post.id)
        posts.value = posts.value.filter((p) => p.id !== post.id)
        if (editingId.value === post.id) {
            cancelEdit()
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Failed to delete the post'
    } finally {
        savingId.value = null
    }
}

function askDelete(post: NewsPostPayload): void {
    confirm.require({
        header: 'Delete post',
        message: 'Delete this post? This cannot be undone.',
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary' },
        accept: () => removePost(post),
    })
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6" @scrollend="onScrollEnd">
            <div class="mx-auto max-w-3xl space-y-3">
                <div
                    v-if="error"
                    class="flex items-center justify-between gap-3 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text"
                >
                    <span>{{ error }}</span>
                    <Button
                        v-if="posts.length === 0"
                        severity="secondary"
                        text
                        size="small"
                        label="Retry"
                        class="shrink-0"
                        @click="retry"
                    />
                </div>

                <article
                    v-for="post in posts"
                    :key="post.id"
                    class="rounded-xl border border-line bg-panel/60 px-4 py-3"
                >
                    <header class="mb-1.5 flex items-start gap-2">
                        <UserAvatar
                            :user-id="post.authorId"
                            :name="post.authorName"
                            :avatar-version="post.authorAvatarVersion"
                            size="md"
                        />
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium text-strong">{{ post.authorName }}</span>
                                <span v-if="post.updatedAt !== post.createdAt" class="text-xs text-faint">
                                    edited
                                </span>
                            </div>
                            <span class="text-xs text-faint">{{ formatTime(post.createdAt) }}</span>
                        </div>
                        <div v-if="canModify(post) && editingId !== post.id" class="ml-auto flex shrink-0 gap-1">
                            <Button
                                severity="secondary"
                                text
                                size="small"
                                label="Edit"
                                @click="startEdit(post)"
                            />
                            <Button
                                severity="danger"
                                text
                                size="small"
                                label="Delete"
                                @click="askDelete(post)"
                            />
                        </div>
                    </header>

                    <div v-if="editingId === post.id" class="space-y-2">
                        <MdEditor v-model="editBody"/>
                        <div class="flex justify-end gap-2">
                            <Button
                                label="Save"
                                :disabled="savingId !== null || editBody.trim() === ''"
                                @click="saveEdit(post)"
                            />
                            <Button severity="secondary" label="Cancel" @click="cancelEdit"/>
                        </div>
                    </div>
                    <div
                        v-else
                        :class="['text-sm text-body', MARKDOWN_CLASS]"
                        v-html="renderMarkdown(post.body)"
                    />
                </article>

                <p v-if="loading" class="py-2 text-center text-xs text-faint">Loading…</p>
                <p
                    v-else-if="posts.length === 0 && !error"
                    class="py-8 text-center text-sm text-faint"
                >
                    No posts yet.
                </p>
                <p
                    v-else-if="loadedAll && posts.length > 0"
                    class="py-2 text-center text-xs text-faint"
                >
                    You're all caught up.
                </p>
            </div>
        </div>

        <div v-if="canPost" class="border-t border-line px-4 py-4 sm:px-6">
            <div class="mx-auto max-w-3xl">
                <MdEditor v-model="composer">
                    <template #actions>
                        <Button
                            severity="secondary"
                            size="small"
                            label="Clear"
                            :disabled="posting || composer === ''"
                            @click="composer = ''"
                        />
                        <Button
                            size="small"
                            label="Publish"
                            :disabled="posting || composer.trim() === ''"
                            @click="submitPost"
                        />
                    </template>
                </MdEditor>
            </div>
        </div>
    </div>
</template>
