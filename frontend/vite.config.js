import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': {
        target: 'http://atlas_nginx:80', // Docker service name for nginx
        changeOrigin: true,
        secure: false,
      }
    }
  }
})
