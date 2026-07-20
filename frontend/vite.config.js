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
// The default (production) build uses the SAME '/app/' base: the Docker image
// copies this build into public/app, and both nginx server blocks (the default
// host, where Laravel's `spa` route mounts it at /app/*, and app.pepyasset.online,
// whose nginx block also serves it nested at /app/ — see .github/workflows/deploy.yml)
// reach it via that path. Do not switch this back to base '/' — the JS/CSS bundle
// would then request assets at the domain root instead of /app/assets/*, which is
// where they're actually served, and the app renders as a blank white screen.
export default defineConfig(() => {
  return {
    plugins: [vue(), tailwindcss()],
    base: '/app/',
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
