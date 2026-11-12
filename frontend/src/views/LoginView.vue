<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'

const email = ref('')
const password = ref('')
</script>

<template>
  <div class="flex min-h-screen items-center justify-center p-4">
    <div class="w-full max-w-sm rounded-2xl border border-zinc-800 bg-zinc-900/60 p-8 shadow-xl">
      <div class="mb-8 text-center">
        <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-sky-600 text-xl font-bold text-white">
          C
        </div>
        <h1 class="text-2xl font-semibold">Canban</h1>
        <p class="mt-1 text-sm text-zinc-400">A small Trello-style board for your projects</p>
      </div>

      <form class="space-y-4" @submit.prevent>
        <div>
          <label for="email" class="mb-1 block text-sm font-medium text-zinc-300">Email</label>
          <InputText id="email" v-model="email" type="email" autocomplete="email" placeholder="you@example.com" />
        </div>
        <div>
          <label for="password" class="mb-1 block text-sm font-medium text-zinc-300">Password</label>
          <InputText id="password" v-model="password" type="password" autocomplete="current-password" placeholder="••••••••" />
        </div>
        <Button type="submit" label="Sign in" class="w-full" disabled />
        <p class="text-center text-xs text-zinc-500">Auth arrives in step 3</p>
      </form>
    </div>
  </div>
</template>
