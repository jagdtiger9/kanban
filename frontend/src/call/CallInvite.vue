<script setup lang="ts">
import Button from 'primevue/button'
import { useCallStore } from './store'

const call = useCallStore()
</script>

<template>
    <div
        v-if="call.invite"
        class="fixed left-1/2 top-4 z-50 w-80 max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-xl border border-line-strong bg-raised p-4 shadow-2xl"
        role="alertdialog"
        aria-live="assertive"
    >
        <p class="text-sm font-semibold text-strong">{{ call.invite.fromName }} is calling</p>
        <p class="mb-3 truncate text-xs text-muted">{{ call.invite.projectName }}</p>
        <div class="flex gap-2">
            <Button size="small" label="Join" class="flex-1" @click="call.acceptInvite()"/>
            <Button size="small" severity="secondary" label="Decline" class="flex-1" @click="call.declineInvite()"/>
        </div>
    </div>

    <div
        v-if="call.error || call.notice"
        class="fixed bottom-4 left-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-2 rounded-lg border px-3 py-2 text-sm shadow-xl"
        :class="call.error ? 'border-danger/40 bg-raised text-danger-text' : 'border-line-strong bg-raised text-body'"
    >
        <span>{{ call.error ?? call.notice }}</span>
        <Button v-if="call.error" size="small" severity="secondary" text label="OK" @click="call.clearError()"/>
    </div>
</template>
