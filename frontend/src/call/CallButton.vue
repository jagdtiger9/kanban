<script setup lang="ts">
import Button from 'primevue/button'
import { useCallStore } from './store'

const props = defineProps<{ projectId: string }>()

const call = useCallStore()
</script>

<template>
    <!-- Hidden while the call channel is not subscribed: calls cannot work then. -->
    <Button
        v-if="call.realtimeReady"
        :severity="call.status === 'joining' ? 'secondary' : 'success'"
        text size="small" title="Call" class="fa-lg"
        :disabled="call.status === 'joining'" @click="call.open(props.projectId)">
        <i class="fa-solid fa-phone"></i>
    </Button>

</template>
