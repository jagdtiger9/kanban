<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import PasswordInput from '../components/PasswordInput.vue'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../api/client'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const rememberMe = ref(false)
const error = ref<string | null>(null)
const submitting = ref(false)

async function submit(): Promise<void> {
    error.value = null
    submitting.value = true
    try {
        await auth.login(email.value, password.value, rememberMe.value)
        router.push({ name: 'board' })
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Something went wrong'
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
                <p class="mt-1 text-sm text-muted">A small Kanban board for your projects</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text">
                    {{ error }}
                </p>
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-body">Email</label>
                    <InputText id="email" v-model="email" type="email" autocomplete="email" placeholder="you@example.com"/>
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-body">Password</label>
                    <PasswordInput id="password" v-model="password" autocomplete="current-password" placeholder="••••••••"/>
                </div>
                <label for="remember-me" class="flex cursor-pointer items-center gap-2 text-sm text-body">
                    <Checkbox v-model="rememberMe" :binary="true" input-id="remember-me"/>
                    Remember me
                </label>
                <Button type="submit" label="Sign in" class="w-full" :disabled="submitting"/>
                <p class="text-center text-sm text-muted">
                    No account yet?
                    <RouterLink to="/register" class="text-brand-text hover:text-brand-text-hover">Create one</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>
