// Global PrimeVue pass-through styles (unstyled mode + Tailwind).
// Only the components actually used are styled; more land as views grow.
export const preset = {
    button: {
        root: 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50',
    },
    inputtext: {
        root: 'w-full rounded-lg border border-line-strong bg-panel px-3 py-2 text-sm text-strong placeholder-faint outline-none focus:border-brand focus:ring-2 focus:ring-brand/30',
    },
    card: {
        body: 'relative p-2',
    },
    checkbox: {
        root: 'inline-flex',
        input:
            'size-4 shrink-0 cursor-pointer appearance-none rounded border border-line-strong bg-panel transition-colors checked:border-brand checked:bg-brand focus-visible:outline-2 focus-visible:outline-brand',
        box: 'hidden',
        icon: 'hidden',
    },
}
