import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/build/' : '/',
  plugins: [vue(), tailwindcss()],
  build: {
    outDir: '../public/build',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'https://kanban.local',
        changeOrigin: true,
        secure: false,
      },
    },
  },
}))
