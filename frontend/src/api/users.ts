import { api } from './client'

export interface UserSuggestion {
    id: string
    email: string
    displayName: string
}

export function searchUsers(term: string): Promise<UserSuggestion[]> {
    return api<UserSuggestion[]>(`/users/search?term=${encodeURIComponent(term)}`)
}
