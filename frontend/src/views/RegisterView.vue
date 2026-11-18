<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../api/client'

const auth = useAuthStore()

const displayName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})
const submitting = ref(false)
const registered = ref(false)

async function submit(): Promise<void> {
    error.value = null
    fieldErrors.value = {}
    submitting.value = true
    try {
        await auth.register(email.value, password.value, passwordConfirmation.value, displayName.value)
        registered.value = true
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
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-2xl border border-line bg-panel/60 p-8 shadow-xl">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-brand text-xl font-bold text-white">
                    C
                </div>
                <h1 class="text-2xl font-semibold">Kanban</h1>
                <p class="mt-1 text-muted">{{ registered ? 'Check your inbox' : 'Create your account' }}</p>
            </div>

            <div v-if="registered" class="space-y-4 text-center">
                <p class="text-body">
                    We sent a confirmation email to <span class="font-medium break-all">{{ email }}</span>.
                </p>
                <p class="text-muted">
                    Open the link in the message to activate your account. Can't find it? Ask a project admin to
                    confirm your account.
                </p>
                <p class="pt-2">
                    <RouterLink to="/login" class="text-brand-text hover:text-brand-text-hover">Back to sign in</RouterLink>
                </p>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submit">
                <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-danger-text">
                    {{ error }}
                </p>
                <div>
                    <label for="display-name" class="mb-1 block font-medium text-body">Display name</label>
                    <InputText id="display-name" v-model="displayName" autocomplete="name" placeholder="Alice" class="w-full"/>
                    <p v-if="fieldErrors.displayName" class="mt-1 text-xs text-danger-text">{{ fieldErrors.displayName }}</p>
                </div>
                <div>
                    <label for="email" class="mb-1 block font-medium text-body">Email</label>
                    <InputText id="email" v-model="email" type="email" autocomplete="email" placeholder="you@example.com" class="w-full"/>
                    <p v-if="fieldErrors.email" class="mt-1 text-xs text-danger-text">{{ fieldErrors.email }}</p>
                </div>
                <div>
                    <label for="password" class="mb-1 block font-medium text-body">Password</label>
                    <Password
                        v-model="password"
                        input-id="password"
                        :input-props="{ autocomplete: 'new-password' }"
                        placeholder="At least 8 characters"
                        toggle-mask
                        :feedback="false"
                        fluid
                    />
                    <p v-if="fieldErrors.password" class="mt-1 text-xs text-danger-text">{{ fieldErrors.password }}</p>
                </div>
                <div>
                    <label for="password-confirmation" class="mb-1 block font-medium text-body">Confirm password</label>
                    <Password
                        v-model="passwordConfirmation"
                        input-id="password-confirmation"
                        :input-props="{ autocomplete: 'new-password' }"
                        placeholder="Repeat the password"
                        toggle-mask
                        :feedback="false"
                        fluid
                    />
                    <p v-if="fieldErrors.passwordConfirmation" class="mt-1 text-xs text-danger-text">
                        {{ fieldErrors.passwordConfirmation }}
                    </p>
                </div>
                <Button type="submit" label="Create account" class="w-full" :disabled="submitting"/>
                <p class="text-center text-muted">
                    Already registered?
                    <RouterLink to="/login" class="text-brand-text hover:text-brand-text-hover">Sign in</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>
