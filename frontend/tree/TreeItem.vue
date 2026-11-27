<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Progress, TreeData } from './types'

defineOptions({ name: 'TreeItem' })

const props = defineProps<{
    data: TreeData
    id: string
    parentId: string | null
    depth: number
    selectedId: string | null
    openIds: Set<string>
    /** this node is inside the selected node's subtree */
    inSelected?: boolean
    progress: (id: string) => Progress
}>()

const emit = defineEmits<{ select: [id: string] }>()

const node = computed(() => props.data.nodes[props.id])
const allKids = computed(() => props.data.children[props.id] ?? [])

// Only the selected node's own line is shown: on the way down from the root just the
// ancestors; the selected node's siblings stay visible; everything inside it is shown.
// With nothing selected the tree is shown as is.
const kids = computed(() => {
    const selected = props.selectedId
    if (selected === null || props.inSelected || props.id === selected) {
        return allKids.value
    }
    if (props.data.parents[selected]?.[0] === props.id) {
        return allKids.value
    }
    return allKids.value.filter(kid => kid === selected || props.openIds.has(kid))
})
const open = ref(false)

// opened from the URL (or Back): unfold the branch down to the selected node
watch(() => props.openIds.has(props.id), (isAncestor) => {
    if (isAncestor) {
        open.value = true
    }
}, { immediate: true })

// a click selects the node and opens / closes its branch;
// a link (node under a non-main parent) only jumps to the node in its main place
function select(): void {
    if (allKids.value.length && !isLink.value) {
        open.value = !open.value
    }
    emit('select', props.id)
}

const columnName = computed(() => props.data.columns.find(c => c.id === node.value.columnId)?.name ?? null)
const isDone = computed(() => node.value.columnId === props.data.doneColumn)

const parents = computed(() => props.data.parents[props.id] ?? [])
// under a non-main parent the node is only a link: blue, no subtree of its own
const isLink = computed(() => props.parentId !== null && parents.value[0] !== props.parentId)

const stat = computed(() => props.progress(props.id))
</script>

<template>
    <li>
        <div class="row" :class="{ selected: selectedId === id && !isLink, link: isLink }"
             @click="select">

            <span class="title" :class="{ done: isDone }">{{ node.title }}</span>
            <span v-if="allKids.length && !isLink" class="nested-inline" :title="'Внутри: ' + allKids.length">▦</span>

        </div>

        <ul v-if="kids.length && open && !isLink">
            <TreeItem v-for="kid in kids" :key="kid" :data="data" :id="kid" :parent-id="id" :depth="depth + 1"
                      :selected-id="selectedId" :open-ids="openIds"
                      :in-selected="inSelected || id === selectedId" :progress="progress" @select="emit('select', $event)"/>
        </ul>
    </li>
</template>
