<script setup lang="ts">
import NewsFeed from '../components/NewsFeed.vue'
</script>

<template>
    <main class="flex min-w-0 flex-1 flex-col">
        <div class="border-b border-line px-4 py-3 sm:px-6 sm:py-4">
            <h1 class="text-xl font-semibold text-strong sm:text-2xl">News</h1>
            <p class="mt-1 text-sm text-muted">News feed - updates, announces.</p>
        </div>
        <NewsFeed/>
    </main>
</template>
