<script setup lang="ts">
import { computed, ref } from 'vue'
import PeopleModal from './PeopleModal.vue'
import UserAvatar from './UserAvatar.vue'
import { avatarColor, buildCardPeople, roleLabel, type CardPerson } from '../utils/people'

const props = defineProps<{
    cardTitle: string
    creatorId: string
    assigneeIds: string[]
    memberNames: Map<string, string>
    memberAvatars: Map<string, string | null>
    memberColors: Map<string, string | null>
}>()

const VISIBLE_LIMIT = 4

const showModal = ref(false)

const people = computed(() => buildCardPeople(props.creatorId, props.assigneeIds, props.memberNames))
const visiblePeople = computed(() => people.value.slice(0, VISIBLE_LIMIT))
const hiddenCount = computed(() => people.value.length - visiblePeople.value.length)

function roleSummary(person: CardPerson): string {
    return person.roles.map(roleLabel).join(', ')
}

function personColor(personId: string): string {
    return props.memberColors.get(personId) ?? avatarColor(personId)
}
</script>

<template>
    <div class="flex items-center -space-x-1.5">
        <button
            v-for="person in visiblePeople"
            :key="person.id"
            type="button"
            class="people-badge cursor-pointer rounded-full ring-2 transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-brand"
            :style="{ '--tw-ring-color': personColor(person.id) }"
            :aria-label="`${person.name ?? 'Unknown member'}: ${roleSummary(person)}`"
            :title="`${person.name ?? 'Unknown member'} — ${roleSummary(person)}`"
            @click.stop="showModal = true"
        >
            <UserAvatar
                :user-id="person.id"
                :name="person.name"
                :avatar-version="memberAvatars.get(person.id) ?? null"
                :color="memberColors.get(person.id) ?? null"
                size="card"
            />
        </button>
        <button
            v-if="hiddenCount > 0"
            type="button"
            class="people-badge flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-full bg-active ring-2 ring-raised text-xs font-semibold text-strong transition-colors hover:bg-line-emphasis focus-visible:outline-2 focus-visible:outline-brand"
            :aria-label="`Show ${hiddenCount} more ${hiddenCount === 1 ? 'person' : 'people'} on this card`"
            :title="`+${hiddenCount} more`"
            @click.stop="showModal = true"
        >
            +{{ hiddenCount }}
        </button>
    </div>
    <PeopleModal
        v-if="showModal"
        :card-title="cardTitle"
        :people="people"
        :member-avatars="memberAvatars"
        :member-colors="memberColors"
        @close="showModal = false"
    />
</template>
