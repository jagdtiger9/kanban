import { api } from './client'

export interface UserSuggestion {
    id: string
    email: string
    displayName: string
}

export function searchUsers(term: string): Promise<UserSuggestion[]> {
    return api<UserSuggestion[]>(`/users/search?term=${encodeURIComponent(term)}`)
}

export interface AdminUserPayload {
    id: string
    email: string
    displayName: string
    isGlobalAdmin: boolean
    confirmed: boolean
    createdAt: string
}

export function listUsers(): Promise<AdminUserPayload[]> {
    return api<AdminUserPayload[]>('/users')
}

export function confirmUser(id: string): Promise<AdminUserPayload> {
    return api<AdminUserPayload>(`/users/${id}/confirm`, { method: 'POST' })
}
