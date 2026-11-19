import { api } from './client'

export type MemberRole = 'admin' | 'member'

export interface ProjectPayload {
    id: string
    name: string
    archived: boolean
    createdAt: string
    role?: MemberRole
    memberCount?: number
    isFavourite?: boolean
}

export interface MemberPayload {
    userId: string
    role: MemberRole
    email: string
    displayName: string
    avatarVersion: string | null
    joinedAt: string
}

export interface ProjectDetail {
    project: ProjectPayload
    boardId: string
    members: MemberPayload[]
}

export interface MemberChangePayload {
    userId: string
    role: MemberRole
    joinedAt: string
}

export function listProjects(): Promise<ProjectPayload[]> {
    return api<ProjectPayload[]>('/projects')
}

export function createProject(name: string): Promise<ProjectPayload> {
    return api<ProjectPayload>('/projects', {
        method: 'POST',
        body: JSON.stringify({ name }),
    })
}

export function getProject(id: string): Promise<ProjectDetail> {
    return api<ProjectDetail>(`/projects/${id}`)
}

export function renameProject(id: string, name: string): Promise<ProjectPayload> {
    return api<ProjectPayload>(`/projects/${id}`, {
        method: 'PUT',
        body: JSON.stringify({ name }),
    })
}

export function archiveProject(id: string): Promise<ProjectPayload> {
    return api<ProjectPayload>(`/projects/${id}/archive`, { method: 'POST' })
}

export function restoreProject(id: string): Promise<ProjectPayload> {
    return api<ProjectPayload>(`/projects/${id}/restore`, { method: 'POST' })
}

export function setProjectFavourite(id: string, isFavourite: boolean): Promise<void> {
    return api<void>(`/projects/${id}/favourite`, {
        method: 'PUT',
        body: JSON.stringify({ isFavourite }),
    })
}

export function deleteProject(id: string): Promise<void> {
    return api<void>(`/projects/${id}`, { method: 'DELETE' })
}

export function addMember(projectId: string, userId: string, role: MemberRole): Promise<MemberChangePayload> {
    return api<MemberChangePayload>(`/projects/${projectId}/members`, {
        method: 'POST',
        body: JSON.stringify({ userId, role }),
    })
}

export function changeMemberRole(
    projectId: string,
    userId: string,
    role: MemberRole,
): Promise<MemberChangePayload> {
    return api<MemberChangePayload>(`/projects/${projectId}/members/${userId}`, {
        method: 'PUT',
        body: JSON.stringify({ role }),
    })
}

export function removeMember(projectId: string, userId: string): Promise<void> {
    return api<void>(`/projects/${projectId}/members/${userId}`, { method: 'DELETE' })
}
