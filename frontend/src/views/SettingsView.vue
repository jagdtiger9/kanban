<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import Password from 'primevue/password'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../api/client'
import { AVATAR_ACCEPT, MAX_AVATAR_BYTES } from '../utils/attachments'
import UserAvatar from '../components/UserAvatar.vue'

const auth = useAuthStore()

const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirmation = ref('')
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})
const submitting = ref(false)
const success = ref<string | null>(null)

const avatarInput = ref<HTMLInputElement | null>(null)
const avatarBusy = ref(false)
const avatarError = ref<string | null>(null)
const avatarSuccess = ref<string | null>(null)

async function onAvatarSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (file === undefined) return

    avatarError.value = null
    avatarSuccess.value = null
    if (!AVATAR_ACCEPT.split(',').includes(file.type)) {
        avatarError.value = 'Choose a JPEG, PNG, WebP, or GIF image'
        return
    }
    if (file.size > MAX_AVATAR_BYTES) {
        avatarError.value = 'The photo must be 5 MB or smaller'
        return
    }

    avatarBusy.value = true
    try {
        await auth.updateAvatar(file)
        avatarSuccess.value = 'Photo updated'
    } catch (e) {
        avatarError.value = e instanceof ApiError ? e.message : 'Failed to upload photo'
    } finally {
        avatarBusy.value = false
    }
}

async function removeAvatar(): Promise<void> {
    avatarError.value = null
    avatarSuccess.value = null
    avatarBusy.value = true
    try {
        await auth.removeAvatar()
        avatarSuccess.value = 'Photo removed'
    } catch (e) {
        avatarError.value = e instanceof ApiError ? e.message : 'Failed to remove photo'
    } finally {
        avatarBusy.value = false
    }
}

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
                <p class="mt-1 text-muted">Manage your photo and password</p>
            </div>

            <div class="mb-8 flex flex-col items-center gap-3">
                <UserAvatar
                    :user-id="auth.user?.id ?? ''"
                    :name="auth.user?.displayName ?? null"
                    :avatar-version="auth.user?.avatarVersion ?? null"
                    size="lg"
                />
                <input
                    ref="avatarInput"
                    type="file"
                    class="hidden"
                    :accept="AVATAR_ACCEPT"
                    @change="onAvatarSelected"
                />
                <div class="flex items-center gap-2">
                    <Button
                        severity="secondary"
                        size="small"
                        label="Upload photo"
                        :disabled="avatarBusy"
                        @click="avatarInput?.click()"
                    />
                    <Button
                        v-if="auth.user?.avatarVersion"
                        severity="danger"
                        text
                        size="small"
                        label="Remove"
                        :disabled="avatarBusy"
                        @click="removeAvatar"
                    />
                </div>
                <p v-if="avatarError" class="text-xs text-danger-text">{{ avatarError }}</p>
                <p v-if="avatarSuccess" class="text-xs text-body">{{ avatarSuccess }}</p>
            </div>

            <div class="mb-6 border-t border-line"></div>

            <form class="space-y-4" @submit.prevent="submit">
                <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                    {{ error }}
                </p>
                <p
                    v-if="success"
                    class="rounded-lg border border-line bg-panel px-3 py-2 text-body"
                >
                    {{ success }}
                </p>
                <div>
                    <label for="current-password" class="mb-1 block font-medium text-body">Current password</label>
                    <Password
                        v-model="currentPassword"
                        input-id="current-password"
                        :input-props="{ autocomplete: 'current-password' }"
                        toggle-mask
                        :feedback="false"
                        fluid
                    />
                    <p v-if="fieldErrors.currentPassword" class="mt-1 text-xs text-danger-text">
                        {{ fieldErrors.currentPassword }}
                    </p>
                </div>
                <div>
                    <label for="new-password" class="mb-1 block font-medium text-body">New password</label>
                    <Password
                        v-model="newPassword"
                        input-id="new-password"
                        :input-props="{ autocomplete: 'new-password' }"
                        placeholder="At least 8 characters"
                        toggle-mask
                        :feedback="false"
                        fluid
                    />
                    <p v-if="fieldErrors.newPassword" class="mt-1 text-xs text-danger-text">
                        {{ fieldErrors.newPassword }}
                    </p>
                </div>
                <div>
                    <label for="new-password-confirmation" class="mb-1 block font-medium text-body">
                        Confirm new password
                    </label>
                    <Password
                        v-model="newPasswordConfirmation"
                        input-id="new-password-confirmation"
                        :input-props="{ autocomplete: 'new-password' }"
                        placeholder="Repeat the new password"
                        toggle-mask
                        :feedback="false"
                        fluid
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
