<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
    <main class="flex flex-1 items-center justify-center p-6">
        <div class="max-w-sm text-center">
            <p class="text-4xl font-bold text-faint">404</p>
            <p class="mt-2 text-lg text-body">Page not found</p>
            <p class="mt-1 break-all text-sm text-faint">{{ route.fullPath }}</p>
            <RouterLink
                to="/"
                class="mt-4 inline-block text-sm font-medium text-brand-text transition-colors hover:text-brand-text-hover"
            >
                Back to the board
            </RouterLink>
        </div>
    </main>
</template>
