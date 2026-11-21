import { inject, onBeforeUnmount, provide, ref, type InjectionKey, type Ref } from 'vue'
import { useConfirm } from 'primevue/useconfirm'

/** One editable piece of a dialog that can hold unsaved edits (a field, the title…). */
export interface UnsavedEditsSource {
    /** Shown in the prompt: "Save your changes (description, new comment)…". */
    label: string
    isDirty: () => boolean
    /** Persists the edits; false when saving failed (the source shows its own error). */
    save: () => Promise<boolean>
    discard: () => void
}

interface UnsavedEditsRegistry {
    register: (source: UnsavedEditsSource) => () => void
}

const REGISTRY_KEY: InjectionKey<UnsavedEditsRegistry> = Symbol('unsavedEdits')

/**
 * Field side: takes part in the closest guarded dialog while mounted.
 * Outside such a dialog it does nothing, so the field stays reusable anywhere.
 */
export function useUnsavedEditsSource(source: UnsavedEditsSource): void {
    const registry = inject(REGISTRY_KEY, null)
    if (registry === null) return
    onBeforeUnmount(registry.register(source))
}

export interface UnsavedEditsGuard {
    /** Escape, the close button or the mask: close, or ask first when something is unsaved. */
    requestClose: () => void
    /** True while the prompt is open (it takes the focus: blur-to-save must not fire). */
    prompting: Ref<boolean>
    /** For sources that live in the guarding component itself (provide/inject skips it). */
    register: (source: UnsavedEditsSource) => () => void
}

/**
 * Dialog side: collects every source below it and, on close, asks
 * "Save / Don't save" when any of them is dirty. Dismissing the prompt keeps editing.
 */
export function useUnsavedEditsGuard(close: () => void): UnsavedEditsGuard {
    const confirm = useConfirm()
    const sources = new Set<UnsavedEditsSource>()
    const prompting = ref(false)

    function register(source: UnsavedEditsSource): () => void {
        sources.add(source)
        return () => {
            sources.delete(source)
        }
    }

    provide(REGISTRY_KEY, { register })

    function dirtyLabels(): string[] {
        return [...sources].filter((source) => source.isDirty()).map((source) => source.label)
    }

    async function saveAllAndClose(): Promise<void> {
        for (const source of [...sources]) {
            if (source.isDirty()) await source.save()
        }
        // Anything still dirty failed to save: stay open, its error is on screen.
        if (dirtyLabels().length === 0) close()
    }

    function requestClose(): void {
        // Escape reaches the dialog again while the prompt is open: ask only once.
        if (prompting.value) return
        const labels = dirtyLabels()
        if (labels.length === 0) {
            close()
            return
        }
        prompting.value = true
        confirm.require({
            header: 'Unsaved changes',
            message: `Save your changes (${labels.join(', ')}) before closing?`,
            acceptProps: { label: 'Save' },
            rejectProps: { label: "Don't save", severity: 'secondary' },
            accept: () => {
                prompting.value = false
                void saveAllAndClose()
            },
            reject: () => {
                // Discard before closing: the prompt hands the focus back to the field
                // it took it from, and that field's blur must find nothing to save.
                for (const source of [...sources]) source.discard()
                prompting.value = false
                close()
            },
            onHide: () => {
                prompting.value = false
            },
        })
    }

    return { requestClose, prompting, register }
}
