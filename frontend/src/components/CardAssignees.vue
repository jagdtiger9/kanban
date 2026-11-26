<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import Button from 'primevue/button'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { Divider } from 'primevue'
import * as boardApi from '../api/board'
import type { CardPayload } from '../api/board'
import type { MemberPayload } from '../api/projects'
import { ApiError } from '../api/client'
import { useAuthStore } from '../stores/auth'
import UserAvatar from './UserAvatar.vue'

const props = defineProps<{
    cardId: string
    members: MemberPayload[]
    assigneeIds: string[]
    executorId: string | null
}>()

const emit = defineEmits<{
    updated: [patch: Partial<CardPayload>]
    error: [message: string | null]
}>()

const auth = useAuthStore()

const selectedAssigneeIds = ref<string[]>([...props.assigneeIds])
const selectedExecutorId = ref<string | null>(props.executorId)

watch(
    () => props.assigneeIds,
    (ids) => {
        selectedAssigneeIds.value = [...ids]
    },
)

watch(
    () => props.executorId,
    (value) => {
        selectedExecutorId.value = value
    },
)

const assigneeOptions = computed(() =>
    props.members.map((member) => ({
        userId: member.userId,
        displayName: member.displayName,
        avatarVersion: member.avatarVersion,
        avatarColor: member.avatarColor,
    })),
)

const selectedAssignees = computed(() =>
    selectedAssigneeIds.value
        .map((id) => props.members.find((member) => member.userId === id))
        .filter((member): member is MemberPayload => member !== undefined),
)

const selectedExecutor = computed((): MemberPayload | undefined =>
    props.members.find((member: MemberPayload) => member.userId === selectedExecutorId.value)
)

async function changeAssignees(next: string[] | null): Promise<void> {
    const userIds = next ?? []
    selectedAssigneeIds.value = userIds
    emit('error', null)
    try {
        await boardApi.setCardAssignees(props.cardId, userIds)
        emit('updated', { assigneeIds: userIds })
    } catch (e) {
        selectedAssigneeIds.value = [...props.assigneeIds]
        emit('error', e instanceof ApiError ? e.message : 'Failed to update assignees')
    }
}

const amIAssignee = computed(() => selectedAssigneeIds.value.find((id) => id === auth.user?.id))
const addMeAssignee = () => {
    if (auth.user?.id) {
        selectedAssigneeIds.value.push(auth.user?.id)
    }
}

function removeAssignee(userId: string): void {
    void changeAssignees(selectedAssigneeIds.value.filter((id) => id !== userId))
}

async function changeExecutor(userId: string | null): Promise<void> {
    selectedExecutorId.value = userId
    emit('error', null)
    try {
        await boardApi.setCardExecutor(props.cardId, userId)
        emit('updated', { executorId: userId })
    } catch (e) {
        selectedExecutorId.value = props.executorId
        emit('error', e instanceof ApiError ? e.message : 'Failed to set executor')
    }
}

function assignExecutorToMe(): void {
    const userId = auth.user?.id
    if (userId === undefined) return
    void changeExecutor(userId)
}

const isAssigneeOpen = ref(false)
const assigneeRef = ref<InstanceType<typeof MultiSelect> | null>(null)

const openAssigneeMultiselect = async () => {
    isAssigneeOpen.value = true
    await nextTick()
    // Open the popup programmatically after the component is mounted
    assigneeRef.value?.show()
}

const closeAssigneeMultiselect = () => {
    // Called when the overlay closes (Escape, outside click, or selection)
    isAssigneeOpen.value = false
}

const isExecutorOpen = ref(false)
const executorRef = ref<InstanceType<typeof Select> | null>(null)

const openExecutorMultiselect = async () => {
    isExecutorOpen.value = true
    await nextTick()
    // Open the popup programmatically after the component is mounted
    executorRef.value?.show()
}

const closeExecutorMultiselect = () => {
    // Called when the overlay closes (Escape, outside click, or selection)
    isExecutorOpen.value = false
}
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <div class="flex items-center gap-2">
                <Button class="p-1 pl-0 text-lg" text @click="openAssigneeMultiselect">+ Assignees</Button>
                <Button
                    severity="secondary"
                    size="small" outlined class="py-1 px-2"
                    label="Add me"
                    v-show="!amIAssignee"
                    @click="addMeAssignee"/>
            </div>
            <div v-if="isAssigneeOpen">
                <MultiSelect
                    v-model="selectedAssigneeIds"
                    ref="assigneeRef"
                    :options="assigneeOptions"
                    option-label="displayName"
                    option-value="userId"
                    :showToggleAll="false"
                    placeholder="No assignees"
                    @update:model-value="changeAssignees"
                    @hide="closeAssigneeMultiselect">
                    <template #option="{ option }">
                        <div class="flex items-center gap-2">
                            <UserAvatar
                                :user-id="option.userId"
                                :name="option.displayName"
                                :avatar-version="option.avatarVersion"
                                :color="option.avatarColor"/>
                            <span>{{ option.displayName }}</span>
                        </div>
                    </template>
                </MultiSelect>
            </div>
            <ul
                v-if="selectedAssignees.length > 0"
                class="max-h-40 space-y-0.5 overflow-y-auto">
                <li
                    v-for="member in selectedAssignees"
                    :key="member.userId"
                    class="flex items-center gap-2 rounded-lg px-2 py-1 text-body">
                    <Button
                        class="pl-0"
                        severity="secondary" text size="small" label="✕"
                        :title="`Unassign ${member.displayName}`"
                        @click="removeAssignee(member.userId)">
                        <i class="fa-solid fa-xmark"></i>
                    </Button>
                    <UserAvatar
                        :user-id="member.userId"
                        :name="member.displayName"
                        :avatar-version="member.avatarVersion"
                        :color="member.avatarColor"/>
                    <span class="min-w-0">{{ member.displayName }}</span>
                </li>
            </ul>
        </div>
        <div>
            <div class="flex items-center gap-2">
                <Button class="p-1 pl-0 text-lg" text @click="openExecutorMultiselect">+ Executor</Button>
                <Button
                    severity="secondary"
                    size="small" outlined class="py-1 px-2"
                    label="Assign to me"
                    v-show="auth.user?.id !== selectedExecutorId"
                    @click="assignExecutorToMe"/>
            </div>
            <div v-if="isExecutorOpen && props.members.length > 0">
                <Select
                    :model-value="selectedExecutorId"
                    :options="assigneeOptions"
                    ref="executorRef"
                    option-label="displayName"
                    option-value="userId"
                    placeholder="No executor"
                    show-clear
                    class="min-w-0 flex-1"
                    @hide="closeExecutorMultiselect"
                    @update:model-value="(value: string | null) => changeExecutor(value)">
                    <template #option="{ option }">
                        <div class="flex items-center gap-2">
                            <UserAvatar
                                :user-id="option.userId"
                                :name="option.displayName"
                                :avatar-version="option.avatarVersion"
                                :color="option.avatarColor"/>
                            <span>{{ option.displayName }}</span>
                        </div>
                    </template>
                </Select>
            </div>
            <div class="flex items-center gap-2" v-if="selectedExecutor">
                <Button
                    class="pl-0"
                    severity="secondary" text size="small" label="✕"
                    :title="`Unassign ${selectedExecutor.displayName}`"
                    @click="changeExecutor(null)">
                    <i class="fa-solid fa-xmark"></i>
                </Button>
                <UserAvatar
                    :user-id="selectedExecutor.userId"
                    :name="selectedExecutor.displayName"
                    :avatar-version="selectedExecutor.avatarVersion"
                    :color="selectedExecutor.avatarColor"/>
                <span class="min-w-0">{{ selectedExecutor.displayName }}</span>
            </div>
        </div>
    </div>
</template>
