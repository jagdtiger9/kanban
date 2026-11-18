<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import CallInvite from './CallInvite.vue'
import CallWindow from './CallWindow.vue'
import { useCallRealtime } from './useCallRealtime'

// Everything call-related that lives above the pages: the personal realtime
// channel, the invitation popup and the call window (survives navigation).
const auth = useAuthStore()
useCallRealtime(computed(() => auth.user?.id ?? null))
</script>

<template>
    <CallWindow/>
    <CallInvite/>
</template>
