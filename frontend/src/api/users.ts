import { api, upload } from './client'

export interface UserSuggestion {
    id: string
    email: string
    displayName: string
    avatarVersion: string | null
    avatarColor: string | null
}

export function searchUsers(term: string): Promise<UserSuggestion[]> {
    return api<UserSuggestion[]>(`/users/search?term=${encodeURIComponent(term)}`)
}

export interface AdminUserPayload {
    id: string
    email: string
    displayName: string
    isGlobalAdmin: boolean
    isNewsMaker: boolean
    confirmed: boolean
    createdAt: string
    avatarVersion: string | null
    avatarColor: string | null
}

export function listUsers(): Promise<AdminUserPayload[]> {
    return api<AdminUserPayload[]>('/users')
}

export function confirmUser(id: string): Promise<AdminUserPayload> {
    return api<AdminUserPayload>(`/users/${id}/confirm`, { method: 'POST' })
}

export function promoteNewsMaker(id: string): Promise<AdminUserPayload> {
    return api<AdminUserPayload>(`/users/${id}/news-maker`, { method: 'POST' })
}

export function demoteNewsMaker(id: string): Promise<AdminUserPayload> {
    return api<AdminUserPayload>(`/users/${id}/news-maker`, { method: 'DELETE' })
}

export interface AvatarPayload {
    avatarVersion: string | null
}

export function uploadAvatar(file: File): Promise<AvatarPayload> {
    const form = new FormData()
    form.append('file', file)
    return upload<AvatarPayload>('/users/me/avatar', form)
}

export function removeAvatar(): Promise<void> {
    return api<void>('/users/me/avatar', { method: 'DELETE' })
}

export interface AvatarColorPayload {
    avatarColor: string | null
}

export function updateAvatarColor(color: string | null): Promise<AvatarColorPayload> {
    return api<AvatarColorPayload>('/users/me/color', {
        method: 'PUT',
        body: JSON.stringify({ color }),
    })
}

export function avatarUrl(userId: string, avatarVersion: string | null): string | null {
    if (avatarVersion === null) {
        return null
    }

    return `/api/users/${userId}/avatar?v=${encodeURIComponent(avatarVersion)}`
}
