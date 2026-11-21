<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import { useCallStore } from './store'
import VideoTile from './VideoTile.vue'

const call = useCallStore()
const showInvite = ref(false)

const candidates = computed(() =>
    call.members.filter((m) => m.userId !== call.selfId && !call.tiles.some((t) => t.userId === m.userId)),
)

// Member list shown before the call starts (status "picking").
const others = computed(() => call.members.filter((m) => m.userId !== call.selfId))

function title(): string {
    if (call.status === 'joining') return 'Joining…'
    // "(v2)": temporary marker to tell which call build is deployed
    if (call.status === 'picking') return call.projectName ? `Call(v2) · ${call.projectName}` : 'Loading…'
    return call.projectName
}

const gridClass = computed(() => (call.tiles.length === 0 ? 'sm:grid-cols-1' : 'sm:grid-cols-2'))
</script>

<template>
    <template v-if="call.status !== 'idle'">
        <!-- Minimized: a small bar, the call keeps running -->
        <div
            v-if="call.minimized"
            class="fixed bottom-4 right-4 z-40 flex items-center gap-2 rounded-xl border border-line-strong bg-raised px-3 py-2 shadow-xl"
        >
            <span class="text-sm text-strong">
                {{ call.projectName || 'Call' }} · {{ call.tiles.length + 1 }}
            </span>
            <Button size="small" severity="secondary" label="Open" @click="call.setMinimized(false)"/>
            <Button size="small" severity="danger" label="Leave" @click="call.leave()"/>
        </div>

        <div
            v-else
            class="fixed inset-0 z-40 flex flex-col bg-panel sm:inset-auto sm:bottom-4 sm:right-4 sm:w-[36rem] sm:max-w-[calc(100vw-2rem)] sm:rounded-xl sm:border sm:border-line-strong sm:shadow-2xl"
        >
            <div class="flex items-center gap-2 border-b border-line px-3 py-2">
                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-strong">
                    {{ title() }}
                </span>
                <Button size="small" severity="secondary" text label="Minimize" @click="call.setMinimized(true)"/>
            </div>

            <!-- Before the call: pick who to call -->
            <div v-if="call.status === 'picking'" class="min-h-0 flex-1 overflow-auto p-3">
                <p class="mb-1 px-1 text-xs font-medium text-muted">Who do you want to call?</p>
                <p v-if="call.projectName && others.length === 0" class="px-1 py-1 text-sm text-faint">
                    No other members in this project.
                </p>
                <ul class="divide-y divide-line">
                    <li v-for="member in others" :key="member.userId">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded px-1 py-2 text-left text-sm text-body hover:bg-raised"
                            @click="call.callMember(member.userId)"
                        >
                            <span class="min-w-0 flex-1 truncate">{{ member.displayName }}</span>
                            <span class="text-xs text-muted">Call</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div v-else class="min-h-0 flex-1 overflow-auto p-3">
                <div class="grid grid-cols-1 gap-2" :class="gridClass">
                    <VideoTile
                        name="You"
                        :stream="call.localVideo"
                        :show-video="call.localVideo !== null"
                        :mirrored="call.local.media.cam && !call.local.media.screen"
                        :mic-off="!call.local.media.mic"
                        muted
                    />
                    <VideoTile
                        v-for="tile in call.tiles"
                        :key="tile.userId"
                        :name="tile.displayName"
                        :stream="tile.stream"
                        :show-video="tile.media.cam || tile.media.screen"
                        :mic-off="!tile.media.mic"
                        :status="tile.status"
                    />
                </div>

                <div v-if="showInvite" class="mt-3 rounded-lg border border-line p-2">
                    <p class="mb-1 px-1 text-xs font-medium text-muted">Invite to the call</p>
                    <p v-if="candidates.length === 0" class="px-1 py-1 text-sm text-faint">
                        Everyone from the project is already here.
                    </p>
                    <ul v-else class="divide-y divide-line">
                        <li v-for="member in candidates" :key="member.userId" class="flex items-center gap-2 px-1 py-1.5">
                            <span class="min-w-0 flex-1 truncate text-sm text-body">{{ member.displayName }}</span>
                            <Button
                                size="small"
                                severity="secondary"
                                label="Invite"
                                @click="call.inviteMember(member.userId)"
                            />
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="call.status === 'picking'" class="flex justify-end border-t border-line px-3 py-2">
                <Button size="small" severity="secondary" label="Close" @click="call.leave()"/>
            </div>

            <div v-else class="flex flex-wrap items-center justify-center gap-2 border-t border-line px-3 py-2">
                <Button
                    size="small"
                    :severity="call.local.media.mic ? 'secondary' : 'warn'"
                    :label="call.local.media.mic ? 'Mute' : 'Unmute'"
                    :disabled="call.status !== 'active'"
                    @click="call.toggleMic()"
                />
                <Button
                    size="small"
                    severity="secondary"
                    :label="call.local.media.cam ? 'Camera off' : 'Camera on'"
                    :disabled="call.status !== 'active'"
                    @click="call.toggleCamera()"
                />
                <Button
                    size="small"
                    severity="secondary"
                    :label="call.local.media.screen ? 'Stop sharing' : 'Share screen'"
                    :disabled="call.status !== 'active'"
                    @click="call.toggleScreen()"
                />
                <Button
                    size="small"
                    severity="secondary"
                    label="Invite"
                    :disabled="call.status !== 'active'"
                    @click="showInvite = !showInvite"
                />
                <Button size="small" severity="danger" label="Leave" @click="call.leave()"/>
            </div>
        </div>
    </template>
</template>
