import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// `--mode artisan` (npm run build:local) builds the SPA with the '/app/' base so
// `php artisan serve` can serve it at http://localhost:8000/app — a single-server
// dev workflow, no separate Vite process. The output still goes to dist/ (NOT into
// public/): a real public/app directory would make PHP's built-in server treat
// '/app' as the script and strip it from deep-link paths, breaking vue-router.
// Laravel's `spa` route serves everything from frontend/dist instead.
//
// The default (production) build is unchanged: base '/', output to dist/ — that is
// what the Docker image copies into public/app and nginx serves at the root of
// app.pepyasset.online. Do NOT change the production branch.
export default defineConfig(({ mode }) => {
  const isLocal = mode === 'artisan'

  return {
    plugins: [vue(), tailwindcss()],
    base: isLocal ? '/app/' : '/',
    server: {
      port: 5173,
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
      },
    },
    build: {
      outDir: 'dist',
      emptyOutDir: true,
    },
  }
})
