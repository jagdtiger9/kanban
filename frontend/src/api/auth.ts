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

export interface MessageResponse {
    message: string
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
): Promise<MessageResponse> {
    return api<MessageResponse>('/auth/register', {
        method: 'POST',
        body: JSON.stringify({ email, password, passwordConfirmation, displayName }),
    })
}

export function confirmEmail(token: string): Promise<MessageResponse> {
    return api<MessageResponse>('/auth/confirm', {
        method: 'POST',
        body: JSON.stringify({ token }),
    })
}

export function changePassword(
    currentPassword: string,
    newPassword: string,
    newPasswordConfirmation: string,
): Promise<MessageResponse> {
    return api<MessageResponse>('/auth/password', {
        method: 'POST',
        body: JSON.stringify({ currentPassword, newPassword, newPasswordConfirmation }),
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
