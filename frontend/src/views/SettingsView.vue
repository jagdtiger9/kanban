<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import PasswordInput from '../components/PasswordInput.vue'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../api/client'

const auth = useAuthStore()

const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirmation = ref('')
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})
const submitting = ref(false)
const success = ref<string | null>(null)

async function submit(): Promise<void> {
    error.value = null
    fieldErrors.value = {}
    success.value = null
    submitting.value = true
    try {
        const response = await auth.changePassword(
            currentPassword.value,
            newPassword.value,
            newPasswordConfirmation.value,
        )
        success.value = response.message
        currentPassword.value = ''
        newPassword.value = ''
        newPasswordConfirmation.value = ''
    } catch (e) {
        if (e instanceof ApiError) {
            for (const fieldError of e.errors) {
                fieldErrors.value[fieldError.property] = fieldError.message
            }
            if (e.errors.length === 0) {
                error.value = e.message
            }
        } else {
            error.value = 'Something went wrong'
        }
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <main class="flex flex-1 items-center justify-center overflow-y-auto p-6">
        <div class="w-full max-w-sm rounded-2xl border border-line bg-panel/60 p-8 shadow-xl">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-semibold">Settings</h1>
                <p class="mt-1 text-sm text-muted">Change your password</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text">
                    {{ error }}
                </p>
                <p
                    v-if="success"
                    class="rounded-lg border border-line bg-panel px-3 py-2 text-sm text-body"
                >
                    {{ success }}
                </p>
                <div>
                    <label for="current-password" class="mb-1 block text-sm font-medium text-body">Current password</label>
                    <PasswordInput
                        id="current-password"
                        v-model="currentPassword"
                        autocomplete="current-password"
                    />
                    <p v-if="fieldErrors.currentPassword" class="mt-1 text-xs text-danger-text">
                        {{ fieldErrors.currentPassword }}
                    </p>
                </div>
                <div>
                    <label for="new-password" class="mb-1 block text-sm font-medium text-body">New password</label>
                    <PasswordInput
                        id="new-password"
                        v-model="newPassword"
                        autocomplete="new-password"
                        placeholder="At least 8 characters"
                    />
                    <p v-if="fieldErrors.newPassword" class="mt-1 text-xs text-danger-text">
                        {{ fieldErrors.newPassword }}
                    </p>
                </div>
                <div>
                    <label for="new-password-confirmation" class="mb-1 block text-sm font-medium text-body">
                        Confirm new password
                    </label>
                    <PasswordInput
                        id="new-password-confirmation"
                        v-model="newPasswordConfirmation"
                        autocomplete="new-password"
                        placeholder="Repeat the new password"
                    />
                    <p v-if="fieldErrors.newPasswordConfirmation" class="mt-1 text-xs text-danger-text">
                        {{ fieldErrors.newPasswordConfirmation }}
                    </p>
                </div>
                <Button type="submit" label="Change password" class="w-full" :disabled="submitting"/>
            </form>
        </div>
    </main>
</template>
