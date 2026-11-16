<script setup lang="ts">
import { computed, ref } from 'vue'
import PeopleModal from './PeopleModal.vue'
import UserAvatar from './UserAvatar.vue'
import { buildCardPeople, roleLabel, type CardPerson } from '../utils/people'

const props = defineProps<{
    cardTitle: string
    creatorId: string
    assigneeIds: string[]
    memberNames: Map<string, string>
}>()

const VISIBLE_LIMIT = 4

const showModal = ref(false)

const people = computed(() => buildCardPeople(props.creatorId, props.assigneeIds, props.memberNames))
const visiblePeople = computed(() => people.value.slice(0, VISIBLE_LIMIT))
const hiddenCount = computed(() => people.value.length - visiblePeople.value.length)

function roleSummary(person: CardPerson): string {
    return person.roles.map(roleLabel).join(', ')
}
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            v-for="person in visiblePeople"
            :key="person.id"
            type="button"
            class="people-badge cursor-pointer rounded-full transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-brand"
            :aria-label="`${person.name ?? 'Unknown member'}: ${roleSummary(person)}`"
            :title="`${person.name ?? 'Unknown member'} — ${roleSummary(person)}`"
            @click.stop="showModal = true"
        >
            <UserAvatar :user-id="person.id" :name="person.name"/>
        </button>
        <button
            v-if="hiddenCount > 0"
            type="button"
            class="people-badge flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full bg-active text-[10px] font-semibold text-strong transition-colors hover:bg-line-emphasis focus-visible:outline-2 focus-visible:outline-brand"
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
        @close="showModal = false"
    />
</template>
