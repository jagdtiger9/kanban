import type { UserPayload } from '../api/auth'

export function isGlobalAdmin(user: UserPayload | null): boolean {
    return user?.roles.includes('ROLE_GLOBAL_ADMIN') ?? false
}

export function isNewsMaker(user: UserPayload | null): boolean {
    return user?.roles.includes('ROLE_NEWS_MAKER') ?? false
}

export function canPostNews(user: UserPayload | null): boolean {
    return isGlobalAdmin(user) || isNewsMaker(user)
}
