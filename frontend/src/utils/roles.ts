import type { UserPayload } from '../api/auth'

export function isGlobalAdmin(user: UserPayload | null): boolean {
    return user?.roles.includes('ROLE_GLOBAL_ADMIN') ?? false
}
