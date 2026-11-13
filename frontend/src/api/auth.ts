import { api } from './client'

export interface UserPayload {
    id: string
    email: string
    displayName: string
    roles: string[]
}

export interface AuthResponse {
    user: UserPayload
    isAuth: boolean
}

export function login(email: string, password: string, rememberMe: boolean): Promise<AuthResponse> {
    return api<AuthResponse>('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password, remember_me: rememberMe }),
    })
}

export function register(
    email: string,
    password: string,
    passwordConfirmation: string,
    displayName: string,
): Promise<AuthResponse> {
    return api<AuthResponse>('/auth/register', {
        method: 'POST',
        body: JSON.stringify({ email, password, passwordConfirmation, displayName }),
    })
}

export function me(): Promise<AuthResponse> {
    return api<AuthResponse>('/auth/me')
}

// The backend answers logout with a 302 to the site root; don't let fetch follow
// it (cross-origin in dev) — the session cookie is cleared server-side either way.
export function logout(): Promise<void> {
    return fetch('/api/auth/logout', {
        method: 'POST',
        credentials: 'same-origin',
        redirect: 'manual',
    }).then(() => undefined)
}
