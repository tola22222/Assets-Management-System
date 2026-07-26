# CLAUDE.md (.claude)

Supplementary guidance for Claude Code in this repo. Read alongside the root [CLAUDE.md](../CLAUDE.md), which has the full architecture writeup — this file is a quick-reference on stack facts and conventions to keep consistent.

## Stack summary

- **Backend**: Laravel 12 / PHP 8.2, in `backend/` (not the repo root — the repo is split into `backend/` and `frontend/` sibling directories). Two route sets share the same models: `backend/routes/web.php` (Blade, session auth) and `backend/routes/api.php` (JSON API, Sanctum bearer tokens, controllers under `app/Http/Controllers/Api/`).
- **Frontend**: Vue 3 SPA in `frontend/`, built with Vite. **UI kit is Vuetify** (`vite-plugin-vuetify`), themed in `frontend/src/plugins/vuetify.js`. Tailwind has been retired from the SPA.
- State: Pinia (`stores/auth.js`, `stores/toast.js`). Routing: `vue-router`. i18n: `vue-i18n` (Composition API, `en.json`/`km.json`). Charts: `chart.js` via `vue-chartjs`. HTTP: single axios instance in `api/http.js`.

## Best practices to follow in this repo

- **Change both sides when needed.** Most domain features exist in both the Blade UI and the Vue SPA. Before considering a backend change "done," check if the corresponding web *and* Api controller both need the update.
- **Vuetify, not custom modals/dropdowns.** Use Vuetify components (`v-btn`, `v-data-table`, `v-dialog`, `v-select`, etc.) for new SPA UI instead of hand-rolling equivalents — the app doesn't use Naive UI, Element Plus, or any other component kit.
- **No Tailwind in the SPA.** It was retired in favor of Vuetify; use Vuetify's own spacing/utility props and classes instead. (The root-level Blade UI is separate and still uses Tailwind CSS v4 via `backend/package.json` — that's unaffected.)
- **Follow existing patterns before inventing new ones** — e.g. table row actions use a `v-btn icon="mdi-dots-vertical"` menu rather than a row of separate buttons; copy from `frontend/src/pages/assets/Index.vue` rather than creating a new style.
- **i18n**: wire new user-facing strings through `t('namespace.key')` with entries in both `en.json` and `km.json` — don't hardcode English in new components.
- **Auth/roles**: roles are a plain string column (`users.role`), not a relation. Always enforce authorization server-side (controller/middleware) — the SPA's route guards are UX-only.
- **API routes**: must stay under the `api.` route-name prefix (`backend/routes/api.php` wraps everything in `Route::name('api.')`) — route caching breaks otherwise.
- **Don't build on orphaned code**: `StockTransferController`, `InventoryController`, `AssetReportController`, `Role` model, `AssetAudit` model are not wired into any route. Grep the route files before assuming a controller is reachable.
- **Testing**: `composer test` / `php artisan test` (run from `backend/`) runs against sqlite `:memory:`, independent of the local dev DB.
- **Code style**: run `./vendor/bin/pint` (from `backend/`) for PHP. No repo-enforced JS/Vue linter is configured in `frontend/package.json` — match existing formatting by hand.
