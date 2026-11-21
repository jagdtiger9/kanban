<script setup lang="ts">
import { computed, ref } from 'vue'
import PeopleModal from './PeopleModal.vue'
import UserAvatar from './UserAvatar.vue'
import { avatarColor, buildCardPeople, roleLabel, type CardPerson } from '../utils/people'

const props = defineProps<{
    cardTitle: string
    creatorId: string
    executorId: string | null
    assigneeIds: string[]
    memberNames: Map<string, string>
    memberAvatars: Map<string, string | null>
    memberColors: Map<string, string | null>
}>()

const VISIBLE_LIMIT = 4

const showModal = ref(false)

const executorPerson = computed<CardPerson | null>(() => {
    if (props.executorId === null) {
        return null
    }

    return {
        id: props.executorId,
        name: props.memberNames.get(props.executorId) ?? null,
        roles: ['executor'],
    }
})
const rightPeople = computed(() =>
    buildCardPeople(
        props.creatorId,
        props.assigneeIds.filter((id) => id !== props.executorId),
        props.memberNames,
    ),
)
const visibleRight = computed(() => rightPeople.value.slice(0, VISIBLE_LIMIT))
const hiddenCount = computed(() => rightPeople.value.length - visibleRight.value.length)
const modalPeople = computed<CardPerson[]>(() =>
    executorPerson.value === null ? rightPeople.value : [executorPerson.value, ...rightPeople.value],
)

function roleSummary(person: CardPerson): string {
    return person.roles.map(roleLabel).join(', ')
}

function personColor(personId: string): string {
    return props.memberColors.get(personId) ?? avatarColor(personId)
}
</script>

<template>
    <div class="flex items-center justify-between w-full">
        <div v-if="rightPeople.length > 0" class="flex items-center">
            <button
                v-for="person in visibleRight"
                :key="person.id"
                type="button"
                class="people-badge cursor-pointer rounded-full ring-3 transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-brand"
                :style="{ '--tw-ring-color': personColor(person.id) }"
                :aria-label="`${person.name ?? 'Unknown member'}: ${roleSummary(person)}`"
                :title="`${person.name ?? 'Unknown member'} — ${roleSummary(person)}`"
                @click.stop="showModal = true">
                <UserAvatar
                    :user-id="person.id"
                    :name="person.name"
                    :avatar-version="memberAvatars.get(person.id) ?? null"
                    :color="memberColors.get(person.id) ?? null"
                    size="card-sm"/>
            </button>
            <button
                v-if="hiddenCount > 0"
                type="button"
                class="people-badge flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-full bg-active ring-3 ring-raised text-xs font-semibold text-strong transition-colors hover:bg-line-emphasis focus-visible:outline-2 focus-visible:outline-brand"
                :aria-label="`Show ${hiddenCount} more ${hiddenCount === 1 ? 'person' : 'people'} on this card`"
                :title="`+${hiddenCount} more`"
                @click.stop="showModal = true">
                +{{ hiddenCount }}
            </button>
        </div>
        <div>
            <button
                v-if="executorPerson !== null"
                type="button"
                class="people-badge shrink-0 cursor-pointer rounded-full ring-3 transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-brand"
                :style="{ '--tw-ring-color': personColor(executorPerson.id) }"
                :aria-label="`${executorPerson.name ?? 'Unknown member'}: ${roleSummary(executorPerson)}`"
                :title="`${executorPerson.name ?? 'Unknown member'} — ${roleSummary(executorPerson)}`"
                @click.stop="showModal = true">
                <UserAvatar
                    :user-id="executorPerson.id"
                    :name="executorPerson.name"
                    :avatar-version="memberAvatars.get(executorPerson.id) ?? null"
                    :color="memberColors.get(executorPerson.id) ?? null"
                    size="card"/>
            </button>
        </div>
    </div>
    <PeopleModal
        v-if="showModal"
        :card-title="cardTitle"
        :people="modalPeople"
        :member-avatars="memberAvatars"
        :member-colors="memberColors"
        @close="showModal = false"
    />
</template>
