<script setup lang="ts">
import { computed } from 'vue'
import Modal from './Modal.vue'
import UserAvatar from './UserAvatar.vue'
import { roleLabel, type CardPerson } from '../utils/people'
import { useAuthStore } from '../stores/auth'

defineProps<{
    cardTitle: string
    people: CardPerson[]
    memberAvatars: Map<string, string | null>
}>()

const emit = defineEmits<{ close: [] }>()

const auth = useAuthStore()
const selfId = computed(() => auth.user?.id ?? null)
</script>

<template>
    <Modal @close="emit('close')">
        <template #title>People on this card</template>
        <p class="mb-3 truncate text-muted">{{ cardTitle }}</p>
        <ul class="divide-y divide-line">
            <li v-for="person in people" :key="person.id" class="flex items-center gap-3 py-2.5">
                <UserAvatar
                    :user-id="person.id"
                    :name="person.name"
                    :avatar-version="memberAvatars.get(person.id) ?? null"
                    size="md"
                />
                <div class="min-w-0">
                    <p class="truncate text-strong">
                        {{ person.name ?? 'Unknown member' }}
                        <span v-if="person.id === selfId" class="ml-1 text-xs text-faint">(you)</span>
                    </p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <span
                            v-for="role in person.roles"
                            :key="role"
                            class="rounded bg-active px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-body uppercase"
                        >
                            {{ roleLabel(role) }}
                        </span>
                    </div>
                </div>
            </li>
        </ul>
    </Modal>
</template>
