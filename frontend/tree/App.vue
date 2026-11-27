<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TreeItem from './TreeItem.vue'
import type { Progress, TreeData } from './types'

const data = ref<TreeData | null>(null)
const error = ref<string | null>(null)
const route = useRoute()
const router = useRouter()

/** The selected node comes from the URL; an unknown id selects nothing. */
const selectedId = computed<string | null>(() => {
    const id = route.params.id
    return typeof id === 'string' && data.value?.nodes[id] ? id : null
})

function select(id: string): void {
    if (id !== selectedId.value) {
        void router.push(`/node/${id}`)
    }
}

/** Ancestors of the selected node along main parents, so the tree opens down to its main place. */
const openIds = computed(() => {
    const result = new Set<string>()
    const d = data.value
    let id = selectedId.value ? d?.parents[selectedId.value]?.[0] : undefined
    while (d && id && !result.has(id)) {
        result.add(id)
        id = d.parents[id]?.[0]
    }
    return result
})

onMounted(async () => {
    try {
        const response = await fetch('./data.json')
        data.value = await response.json() as TreeData
    } catch {
        error.value = 'Не удалось загрузить data.json'
    }
})

/** Done / total over the distinct real cards below a node; rejected (Arch) cards do not count. */
function progress(id: string): Progress {
    const d = data.value
    if (d === null) {
        return { done: 0, total: 0 }
    }
    const seen = new Set<string>()
    const walk = (nodeId: string): void => {
        for (const kid of d.children[nodeId] ?? []) {
            if (seen.has(kid)) {
                continue
            }
            seen.add(kid)
            walk(kid)
        }
    }
    walk(id)
    let done = 0
    let total = 0
    for (const nodeId of seen) {
        const node = d.nodes[nodeId]
        if (d.types[node.type].view !== 'card' || node.columnId === d.rejectedColumn) {
            continue
        }
        total++
        if (node.columnId === d.doneColumn) {
            done++
        }
    }
    return { done, total }
}

const selected = computed(() => (data.value && selectedId.value) ? data.value.nodes[selectedId.value] : null)
const selectedKids = computed(() => (data.value && selectedId.value)
    ? (data.value.children[selectedId.value] ?? []).map(k => data.value!.nodes[k])
    : [])
// a card's children: columns become its own board, the rest are subtasks
const kidColumns = computed(() => data.value ? selectedKids.value.filter(k => data.value!.types[k.type].view === 'column') : [])
const kidTasks = computed(() => data.value ? selectedKids.value.filter(k => data.value!.types[k.type].view !== 'column') : [])
/** Path from the root to the selected node along main parents (the first parent of each). */
const crumbs = computed(() => {
    const d = data.value
    const path: { id: string, title: string }[] = []
    let id = selectedId.value
    while (d && id && path.length < 50) {
        path.unshift({ id, title: d.nodes[id].title })
        id = d.parents[id]?.[0] ?? null
    }
    return path
})
const view = computed(() => (data.value && selected.value) ? data.value.types[selected.value.type].view : null)
const selectedColumn = computed(() => data.value?.columns.find(c => c.id === selected.value?.columnId)?.name ?? null)
const selectedParents = computed(() => (data.value && selectedId.value)
    ? (data.value.parents[selectedId.value] ?? []).map(p => ({ id: p, title: data.value!.nodes[p].title }))
    : [])
</script>

<template>
    <p v-if="error" class="error">{{ error }}</p>

    <div v-else-if="data" class="layout">
        <aside class="tree">
            <ul class="root">
                <TreeItem :data="data" :id="data.root" :parent-id="null" :depth="0" :selected-id="selectedId"
                          :open-ids="openIds"
                          :progress="progress" @select="select"/>
            </ul>
        </aside>

        <main class="card">
            <nav v-if="crumbs.length > 1" class="crumbs">
                <template v-for="(crumb, i) in crumbs" :key="crumb.id">
                    <span v-if="i > 0" class="sep">›</span>
                    <a v-if="i < crumbs.length - 1" href="#" @click.prevent="select(crumb.id)">{{ crumb.title }}</a>
                    <b v-else>{{ crumb.title }}</b>
                </template>
            </nav>
            <!-- the look comes from the node's type (data.types[type].view) -->
            <!-- view column: the column itself, children stacked vertically -->
            <div v-if="selected && view === 'column'" class="board">
                <section class="board-column board-column-single">
                    <h3>{{ selected.title }}</h3>
                    <div v-for="kid in selectedKids" :key="kid.id" class="board-card" @click.stop="select(kid.id)">
                        <span v-if="data.children[kid.id]?.length" class="nested"
                              :title="'Внутри: ' + data.children[kid.id].length">▦</span>
                        {{ kid.title }}
                    </div>
                </section>
            </div>
            <!-- view board: children side by side as columns -->
            <div v-else-if="selected && view === 'board'" class="board">
                <section v-for="kid in selectedKids" :key="kid.id" class="board-column"
                         @click="select(kid.id)">
                    <h3>{{ kid.title }}</h3>
                    <div v-for="grandkid in data.children[kid.id] ?? []" :key="grandkid" class="board-card"
                         @click.stop="select(grandkid)">
                        <span v-if="data.children[grandkid]?.length" class="nested"
                              :title="'Внутри: ' + data.children[grandkid].length">▦</span>
                        {{ data.nodes[grandkid].title }}
                    </div>
                </section>
            </div>
            <!-- view card: title, description, subtasks -->
            <template v-else-if="selected">
                <h2>{{ selected.title }}</h2>
                <p class="meta">
                    <span v-if="selectedColumn" class="column">{{ selectedColumn }}</span>
                    <!-- main parent: ⌂ in black (it is also in the crumbs); other parents: ↗ blue links -->
                    <span v-if="selectedParents.length" class="parent" title="Главный родитель">
                        <span class="parent-icon">⌂</span><b>{{ selectedParents[0].title }}</b>
                    </span>
                    <a v-for="parent in selectedParents.slice(1)" :key="parent.id" href="#" class="parent parent-link"
                       title="Ещё родитель" @click.prevent="select(parent.id)">
                        <span class="parent-icon">↗</span>{{ parent.title }}
                    </a>
                </p>
                <pre v-if="selected.description" class="description">{{ selected.description }}</pre>
                <p v-else class="empty">Описания нет</p>
                <div v-if="kidColumns.length" class="board inner-board">
                    <section v-for="kid in kidColumns" :key="kid.id" class="board-column" @click="select(kid.id)">
                        <h3>{{ kid.title }}</h3>
                        <div v-for="grandkid in data.children[kid.id] ?? []" :key="grandkid" class="board-card"
                             @click.stop="select(grandkid)">
                            <span v-if="data.children[grandkid]?.length" class="nested"
                                  :title="'Внутри: ' + data.children[grandkid].length">▦</span>
                            {{ data.nodes[grandkid].title }}
                        </div>
                    </section>
                </div>
                <div v-if="kidTasks.length" class="subtasks">
                    <h3>Подзадачи</h3>
                    <div v-for="kid in kidTasks" :key="kid.id" class="board-card" @click="select(kid.id)">
                        <span v-if="data.children[kid.id]?.length" class="nested"
                              :title="'Внутри: ' + data.children[kid.id].length">▦</span>
                        {{ kid.title }}
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>
