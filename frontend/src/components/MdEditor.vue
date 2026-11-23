<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { MdEditor } from 'md-editor-v3'
import type { ToolbarNames } from 'md-editor-v3'
import DOMPurify from 'dompurify'
import 'md-editor-v3/lib/style.css'
import { useTheme } from '../composables/useTheme'

const content = defineModel<string>({ required: true })

// The default keeps the compact inline editor (news feed); the full editor
// dialog takes more height.
const props = withDefaults(defineProps<{ height?: string }>(), { height: '12rem' })

const { isDark } = useTheme()
const theme = computed(() => (isDark.value ? 'dark' : 'light'))

const slots = useSlots()

const baseToolbars: ToolbarNames[] = [
    'bold',
    'underline',
    'italic',
    '-',
    'strikeThrough',
    'title',
    'sub',
    'sup',
    'quote',
    'unorderedList',
    'orderedList',
    'task',
    '-',
    'codeRow',
    'code',
    'link',
    'table',
    '-',
    'revoke',
    'next',
]

const toolbars = computed<ToolbarNames[]>(() =>
    slots.actions === undefined ? baseToolbars : [...baseToolbars, '=', 0],
)

function sanitize(html: string): string {
    return DOMPurify.sanitize(html)
}
</script>

<template>
    <!-- Inline height: md-editor-v3's unlayered .md-editor{height:500px} would
         otherwise beat Tailwind's layered h-40 utility. -->
    <MdEditor
        v-model="content"
        :theme="theme"
        :sanitize="sanitize"
        :toolbars="toolbars"
        :preview="false"
        :footers="[]"
        language="en-US"
        :scroll-auto="true"
        :show-code-row-number="true"
        input-box-width="100%"
        :style="{ height: props.height }">
        <template #defToolbars>
            <div class="flex items-center gap-2 pr-1">
                <slot name="actions"/>
            </div>
        </template>
    </MdEditor>
</template>
