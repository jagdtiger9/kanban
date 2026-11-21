<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import { getMcpInfo, issueMcpToken, type McpTokenResponse } from '../api/mcp'
import { ApiError } from '../api/client'

const serverUrl = `${window.location.origin}/api/mcp`

// null until loaded; a failed load just hides the line
const version = ref<string | null>(null)

onMounted(async () => {
    try {
        version.value = (await getMcpInfo()).version
    } catch {
        version.value = null
    }
})

const issued = ref<McpTokenResponse | null>(null)
const error = ref<string | null>(null)
const submitting = ref(false)
const copied = ref<'url' | 'token' | null>(null)

async function createToken(): Promise<void> {
    error.value = null
    submitting.value = true
    try {
        issued.value = await issueMcpToken()
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Something went wrong'
    } finally {
        submitting.value = false
    }
}

async function copy(what: 'url' | 'token', text: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text)
        copied.value = what
        setTimeout(() => {
            copied.value = null
        }, 1500)
    } catch {
        // clipboard unavailable (e.g. plain http) — the text stays selectable
    }
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString()
}
</script>

<template>
    <section class="w-full max-w-sm rounded-2xl border border-line bg-panel/60 p-8 shadow-xl">
        <div class="mb-6 text-center">
            <h2 class="text-2xl font-semibold">MCP</h2>
            <p class="mt-1 text-sm text-muted">Connect a CLI agent (Claude Code, Codex, Gemini…) to your boards</p>
            <p v-if="version !== null" class="mt-1 text-xs text-faint">Server version {{ version }}</p>
        </div>

        <div class="space-y-4">
            <p v-if="error" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger-text">
                {{ error }}
            </p>

            <div>
                <span class="mb-1 block text-sm font-medium text-body">Server URL</span>
                <div class="flex items-center gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-lg border border-line bg-panel px-3 py-2 text-sm">
                        {{ serverUrl }}
                    </code>
                    <Button
                        :label="copied === 'url' ? 'Copied' : 'Copy'"
                        size="small"
                        severity="secondary"
                        @click="copy('url', serverUrl)"
                    />
                </div>
            </div>

            <div v-if="issued">
                <span class="mb-1 block text-sm font-medium text-body">Your token</span>
                <p class="mb-2 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-xs text-danger-text">
                    Copy it now — it will not be shown again. Valid until {{ formatDate(issued.expiresAt) }}.
                </p>
                <div class="flex items-center gap-2">
                    <code class="min-w-0 flex-1 break-all rounded-lg border border-line bg-panel px-3 py-2 text-xs">
                        {{ issued.token }}
                    </code>
                    <Button
                        :label="copied === 'token' ? 'Copied' : 'Copy'"
                        size="small"
                        severity="secondary"
                        @click="copy('token', issued.token)"
                    />
                </div>
            </div>

            <Button
                :label="issued ? 'Create a new token' : 'Create token'"
                class="w-full"
                :disabled="submitting"
                @click="createToken"
            />
            <p class="text-xs text-muted">A new token replaces the previous one: agents using the old token stop working.</p>
        </div>
    </section>
</template>
