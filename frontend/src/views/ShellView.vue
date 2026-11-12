<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ping } from '../api/client'

type ApiState = 'checking' | 'ok' | 'down'

const apiState = ref<ApiState>('checking')

onMounted(async () => {
  try {
    await ping()
    apiState.value = 'ok'
  } catch {
    apiState.value = 'down'
  }
})
</script>

<template>
  <div class="flex h-screen flex-col">
    <header class="flex h-14 items-center justify-between border-b border-zinc-800 bg-zinc-900/60 px-4">
      <div class="flex items-center gap-3">
        <div class="flex size-8 items-center justify-center rounded-lg bg-sky-600 text-sm font-bold text-white">C</div>
        <span class="font-semibold">Canban</span>
      </div>
      <div class="flex items-center gap-3">
        <span class="flex items-center gap-1.5 text-xs text-zinc-400">
          <span
            class="size-2 rounded-full"
            :class="apiState === 'ok' ? 'bg-emerald-500' : apiState === 'down' ? 'bg-red-500' : 'bg-amber-500'"
          />
          {{ apiState === 'ok' ? 'api ok' : apiState === 'down' ? 'api down' : 'checking api…' }}
        </span>
        <div class="flex size-8 items-center justify-center rounded-full bg-zinc-700 text-xs font-semibold text-zinc-200">
          U
        </div>
      </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
      <aside class="w-60 shrink-0 border-r border-zinc-800 bg-zinc-900/40 p-4">
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Projects</h2>
        <p class="text-sm text-zinc-500">No projects yet — they arrive in step 4.</p>
      </aside>

      <main class="flex flex-1 items-center justify-center p-6">
        <div class="text-center">
          <p class="text-lg text-zinc-300">Board area</p>
          <p class="mt-1 text-sm text-zinc-500">Columns and cards land in steps 5–6.</p>
        </div>
      </main>
    </div>
  </div>
</template>
