<script setup lang="ts">
import { ref } from 'vue'
import Popover from 'primevue/popover'
import UserAvatar from './UserAvatar.vue'

/** A project member as the picker shows it. */
export interface MemberOption {
    userId: string
    displayName: string
    avatarVersion: string | null
    avatarColor: string | null
}

/**
 * A small pop-up list of project members (avatar + name): a click picks one and
 * closes the list. Opened by the caller with `toggle(event)` from a button.
 * Used for card assignees ("+ Add") and the executor.
 */
const props = withDefaults(defineProps<{ members: MemberOption[]; selectedIds?: string[] }>(), {
    selectedIds: () => [],
})

const emit = defineEmits<{ pick: [userId: string] }>()

const popover = ref<{ toggle: (event: Event) => void; hide: () => void } | null>(null)

function pick(userId: string): void {
    popover.value?.hide()
    emit('pick', userId)
}

defineExpose({ toggle: (event: Event) => popover.value?.toggle(event) })
</script>

<template>
    <Popover ref="popover">
        <ul class="max-h-64 min-w-48 overflow-y-auto py-1" role="listbox">
            <li v-for="member in props.members" :key="member.userId">
                <button
                    type="button"
                    role="option"
                    :aria-selected="props.selectedIds.includes(member.userId)"
                    class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-body hover:bg-raised"
                    @click="pick(member.userId)">
                    <UserAvatar
                        :user-id="member.userId"
                        :name="member.displayName"
                        :avatar-version="member.avatarVersion"
                        :color="member.avatarColor"/>
                    <span class="min-w-0 flex-1 truncate">{{ member.displayName }}</span>
                    <i v-if="props.selectedIds.includes(member.userId)" class="fa-solid fa-check text-brand-text"></i>
                </button>
            </li>
            <li v-if="props.members.length === 0" class="px-2 py-1.5 text-xs text-faint">No members to add.</li>
        </ul>
    </Popover>
</template>
