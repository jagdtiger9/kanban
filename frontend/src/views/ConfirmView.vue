<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { confirmEmail } from '../api/auth'
import { ApiError } from '../api/client'

type State = 'loading' | 'success' | 'error'

const route = useRoute()
const state = ref<State>('loading')
const message = ref('')

onMounted(async () => {
    try {
        const response = await confirmEmail(String(route.params.token))
        message.value = response.message
        state.value = 'success'
    } catch (e) {
        message.value = e instanceof ApiError ? e.message : 'Something went wrong'
        state.value = 'error'
    }
})
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-2xl border border-line bg-panel/60 p-8 shadow-xl">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-brand text-xl font-bold text-white">
                    C
                </div>
                <h1 class="text-2xl font-semibold">Canban</h1>
                <p class="mt-1 text-sm text-muted">Email confirmation</p>
            </div>

            <div v-if="state === 'loading'" class="text-center text-sm text-muted">
                Confirming your email address…
            </div>

            <div v-else-if="state === 'success'" class="space-y-4 text-center">
                <p class="rounded-lg border border-line bg-panel px-3 py-2 text-sm text-body">
                    {{ message }}
                </p>
                <p class="text-sm">
                    <RouterLink to="/login" class="text-brand-text hover:text-brand-text-hover">Continue to sign in</RouterLink>
                </p>
            </div>

            <div v-else class="space-y-4 text-center">
                <p class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text">
                    {{ message }}
                </p>
                <p class="text-sm text-muted">
                    If the link has expired, ask a project admin to confirm your account.
                </p>
                <p class="text-sm">
                    <RouterLink to="/login" class="text-brand-text hover:text-brand-text-hover">Back to sign in</RouterLink>
                </p>
            </div>
        </div>
    </div>
</template>
