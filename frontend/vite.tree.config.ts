import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Hierarchy demo (mk/docs/tz/tz_hierarchy_demo01.md): standalone page, not part of the SPA.
// Build: corepack yarn vite build --config vite.tree.config.ts → public/tree/
export default defineConfig({
    root: 'tree',
    base: '/tree/',
    plugins: [vue()],
    build: {
        outDir: '../../public/tree',
        emptyOutDir: true,
    },
})
