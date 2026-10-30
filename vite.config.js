import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { basename } from 'node:path'
import { fileURLToPath } from 'node:url'

// WAMP: il progetto si trova direttamente dentro www.
// La cartella può chiamarsi ai-live, orizon-ai oppure actualizacion.
const projectFolder = basename(fileURLToPath(new URL('../', import.meta.url)))
const backendPath = `/${encodeURIComponent(projectFolder)}/backend/public`

export default defineConfig({
  plugins: [vue()],
  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1',
        changeOrigin: true,
        timeout: 180000,
        proxyTimeout: 180000,
        rewrite: (path) => `${backendPath}${path.replace(/^\/api/, '')}`,
      },
    },
  },
})
