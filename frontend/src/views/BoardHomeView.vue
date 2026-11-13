<script setup lang="ts">
import { computed } from 'vue'
import { useProjectsStore } from '../stores/projects'

const projects = useProjectsStore()

const hasProjects = computed(() => projects.activeProjects.length > 0)
</script>

<template>
    <main class="flex flex-1 items-center justify-center p-6">
        <div class="max-w-sm text-center">
            <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-xl bg-brand text-xl font-bold text-white">
                C
            </div>
            <p v-if="!projects.initialized" class="text-sm text-faint">Loading projects…</p>
            <template v-else-if="projects.error">
                <p class="text-lg text-body">Couldn't load your projects</p>
                <p class="mt-1 text-sm text-faint">Check your connection and refresh the page.</p>
            </template>
            <template v-else-if="hasProjects">
                <p class="text-lg text-body">Pick a project</p>
                <p class="mt-1 text-sm text-faint">Choose one from the sidebar to open its board.</p>
            </template>
            <template v-else>
                <p class="text-lg text-body">Welcome to Canban</p>
                <p class="mt-1 text-sm text-faint">
                    You're not on any project yet. Create one from the sidebar — it comes with To Do,
                    In Progress and Done columns.
                </p>
            </template>
        </div>
    </main>
</template>
