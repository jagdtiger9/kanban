<script setup lang="ts">
import ConfirmDialog from 'primevue/confirmdialog'
import ProgressBar from 'primevue/progressbar'
import Toast from 'primevue/toast'
import { useLoader } from './composables/useLoader'

const { showFetchLoader } = useLoader()
</script>

<template>
    <ProgressBar
        v-if="showFetchLoader"
        mode="indeterminate"
        class="fixed inset-x-0 top-0 z-50"
        style="height: 3px"/>
    <RouterView/>
    <ConfirmDialog/>
    <Toast position="top-right"/>
</template>
