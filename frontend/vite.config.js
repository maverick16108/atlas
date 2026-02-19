import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// Inside Docker, 'localhost' is the container itself — use 'nginx' service name
const isDocker = process.env.DOCKER === '1'
const apiTarget = isDocker ? 'http://nginx:80' : 'http://localhost:80'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    proxy: {
      '/api': {
        target: apiTarget,
        changeOrigin: true,
        secure: false,
      },
      '/storage': {
        target: apiTarget,
        changeOrigin: true,
        secure: false,
      },
      '/app': {
        target: apiTarget,
        changeOrigin: true,
        secure: false,
        ws: true,
      },
      '/apps': {
        target: apiTarget,
        changeOrigin: true,
        secure: false,
        ws: true,
      }
    }
  }
})
