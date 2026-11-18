<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import type { ProjectPayload } from '../api/projects'

const props = defineProps<{
    project: ProjectPayload
    canDelete: boolean
}>()

const emit = defineEmits<{
    favourite: [isFavourite: boolean]
    members: []
    rename: []
    archive: []
    delete: []
}>()

const menuOpen = ref(false)

const memberCountLabel = computed(
    () => `${props.project.memberCount} ${props.project.memberCount === 1 ? 'member' : 'members'}`,
)

function onDocumentClick(): void {
    menuOpen.value = false
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))

function openMembers(): void {
    menuOpen.value = false
    emit('members')
}

function openRename(): void {
    menuOpen.value = false
    emit('rename')
}

function openArchive(): void {
    menuOpen.value = false
    emit('archive')
}

function openDelete(): void {
    menuOpen.value = false
    emit('delete')
}
</script>

<template>
    <li class="relative">
        <div
            class="group flex items-center rounded-lg transition-colors"
            :class="project.isFavourite ? 'bg-warn/10' : 'hover:bg-raised'"
        >
            <button
                v-if="project.isFavourite !== undefined"
                type="button"
                class="ml-1 shrink-0 cursor-pointer rounded leading-none transition-colors"
                :class="project.isFavourite ? 'text-warn' : 'text-faint hover:text-body'"
                :aria-label="project.isFavourite ? 'Remove from favourites' : 'Add to favourites'"
                :aria-pressed="project.isFavourite"
                :title="project.isFavourite ? 'Remove from favourites' : 'Add to favourites'"
                @click="emit('favourite', !project.isFavourite)"
            >
                {{ project.isFavourite ? '★' : '☆' }}
            </button>
            <RouterLink
                :to="{ name: 'project', params: { id: project.id } }"
                class="min-w-0 flex-1 truncate py-1.5 text-body transition-colors hover:text-strong"
                :class="project.isFavourite === undefined ? 'px-2' : 'pl-1 pr-2'"
            >
                {{ project.name }}
            </RouterLink>
            <button
                v-if="project.memberCount !== undefined"
                type="button"
                class="mr-1 shrink-0 cursor-pointer rounded-full px-1.5 py-0.5 text-[10px] font-medium transition-colors"
                :class="project.role === 'admin' ? 'bg-brand/15 text-brand-text' : 'bg-raised text-muted'"
                :aria-label="memberCountLabel"
                :title="memberCountLabel"
                @click="openMembers"
            >
                {{ project.memberCount }}
            </button>
            <Button
                aria-label="Project actions"
                severity="secondary"
                text
                size="small"
                class="opacity-100 focus-visible:opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                label="⋯"
                @click.stop="menuOpen = !menuOpen"
            />
        </div>
        <div
            v-if="menuOpen"
            class="absolute right-0 top-full z-10 mt-1 w-36 rounded-lg border border-line-strong bg-raised py-1 shadow-xl"
        >
            <Button
                severity="secondary"
                text
                size="small"
                class="w-full justify-start"
                label="Members"
                @click="openMembers"
            />
            <Button
                severity="secondary"
                text
                size="small"
                class="w-full justify-start"
                label="Rename"
                @click="openRename"
            />
            <Button
                severity="secondary"
                text
                size="small"
                class="w-full justify-start"
                label="Archive"
                @click="openArchive"
            />
            <Button
                v-if="canDelete"
                severity="danger"
                text
                size="small"
                class="w-full justify-start"
                label="Delete"
                @click="openDelete"
            />
        </div>
    </li>
</template>
