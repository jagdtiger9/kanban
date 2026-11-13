import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import * as authApi from '../api/auth'
import type { MessageResponse, UserPayload } from '../api/auth'

export const useAuthStore = defineStore('auth', () => {
    const user = ref<UserPayload | null>(null)
    const initialized = ref(false)

    const isAuth = computed(() => user.value !== null)

    async function fetchMe(): Promise<void> {
        try {
            user.value = (await authApi.me()).user
        } catch {
            user.value = null
        } finally {
            initialized.value = true
        }
    }

    async function login(email: string, password: string, rememberMe: boolean): Promise<void> {
        const response = await authApi.login(email, password, rememberMe)
        user.value = response.user
        initialized.value = true
    }

    async function register(
        email: string,
        password: string,
        passwordConfirmation: string,
        displayName: string,
    ): Promise<MessageResponse> {
        return authApi.register(email, password, passwordConfirmation, displayName)
    }

    async function changePassword(
        currentPassword: string,
        newPassword: string,
        newPasswordConfirmation: string,
    ): Promise<MessageResponse> {
        return authApi.changePassword(currentPassword, newPassword, newPasswordConfirmation)
    }

    async function logout(): Promise<void> {
        await authApi.logout()
        user.value = null
    }

    return { user, initialized, isAuth, fetchMe, login, register, changePassword, logout }
})
