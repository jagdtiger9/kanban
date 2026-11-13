import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import * as projectsApi from '../api/projects'
import type { ProjectPayload } from '../api/projects'

export const useProjectsStore = defineStore('projects', () => {
    const projects = ref<ProjectPayload[]>([])
    const initialized = ref(false)
    const error = ref<string | null>(null)

    const activeProjects = computed(() => projects.value.filter((p) => !p.archived))
    const archivedProjects = computed(() => projects.value.filter((p) => p.archived))

    async function fetchProjects(): Promise<void> {
        try {
            projects.value = await projectsApi.listProjects()
            error.value = null
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load projects'
        } finally {
            initialized.value = true
        }
    }

    async function createProject(name: string): Promise<ProjectPayload> {
        const project = await projectsApi.createProject(name)
        projects.value.push(project)

        return project
    }

    async function renameProject(id: string, name: string): Promise<void> {
        replace(await projectsApi.renameProject(id, name))
    }

    async function archiveProject(id: string): Promise<void> {
        replace(await projectsApi.archiveProject(id))
    }

    async function restoreProject(id: string): Promise<void> {
        replace(await projectsApi.restoreProject(id))
    }

    async function deleteProject(id: string): Promise<void> {
        await projectsApi.deleteProject(id)
        projects.value = projects.value.filter((p) => p.id !== id)
    }

    function replace(updated: ProjectPayload): void {
        const index = projects.value.findIndex((p) => p.id === updated.id)
        if (index >= 0) {
            projects.value[index] = updated
        }
    }

    return {
        projects,
        initialized,
        error,
        activeProjects,
        archivedProjects,
        fetchProjects,
        createProject,
        renameProject,
        archiveProject,
        restoreProject,
        deleteProject,
    }
})
