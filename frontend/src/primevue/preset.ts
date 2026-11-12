// Global PrimeVue pass-through styles (unstyled mode + Tailwind).
// Only the components actually used are styled; more land as views grow.
export const preset = {
  button: {
    root: 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-sky-500 focus-visible:outline-2 focus-visible:outline-sky-400 disabled:cursor-not-allowed disabled:opacity-50',
  },
  inputtext: {
    root: 'w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30',
  },
}
