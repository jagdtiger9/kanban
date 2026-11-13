import { computed, readonly, ref } from 'vue'

export type Theme = 'dark' | 'light'

const STORAGE_KEY = 'canban-theme'

const theme = ref<Theme>(
    document.documentElement.classList.contains('light') ? 'light' : 'dark',
)

export function useTheme() {
    const isDark = computed(() => theme.value === 'dark')

    function setTheme(next: Theme): void {
        theme.value = next
        document.documentElement.classList.toggle('dark', next === 'dark')
        document.documentElement.classList.toggle('light', next === 'light')
        localStorage.setItem(STORAGE_KEY, next)
    }

    function toggleTheme(): void {
        setTheme(isDark.value ? 'light' : 'dark')
    }

    return { theme: readonly(theme), isDark, setTheme, toggleTheme }
}
