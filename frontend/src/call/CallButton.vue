<script setup lang="ts">
import Button from 'primevue/button'
import { useCallStore } from './store'

const props = defineProps<{ projectId: string }>()

const call = useCallStore()
</script>

<template>
    <Button
        size="small"
        severity="secondary"
        label="Call"
        :disabled="call.status === 'joining'"
        @click="call.open(props.projectId)"
    />
</template>
