# CLAUDE.md (.claude)

Supplementary guidance for Claude Code in this repo. Read alongside the root [CLAUDE.md](../CLAUDE.md), which has the full architecture writeup — this file is a quick-reference on stack facts and conventions to keep consistent.

## Stack summary

- **Backend**: Laravel 12 / PHP 8.2. Two route sets share the same models: `routes/web.php` (Blade, session auth) and `routes/api.php` (JSON API, Sanctum bearer tokens, controllers under `app/Http/Controllers/Api/`).
- **Frontend**: Vue 3 SPA in `frontend/`, built with Vite. **UI component 
- State: Pinia (`stores/auth.js`, `stores/toast.js`). Routing: `vue-router`. i18n: `vue-i18n` (Composition API, `en.json`/`km.json`). Charts: `chart.js` via `vue-chartjs`. HTTP: single axios instance in `api/http.js`.

## Best practices to follow in this repo

- **Change both sides when needed.** Most domain features exist in both the Blade UI and the Vue SPA. Before considering a backend change "done," check if the corresponding web *and* Api controller both need the update.
- **Naive UI, not custom modals/dropdowns.** Use Naive UI components (`n-button`, `n-data-table`, `n-modal`, `n-select`, etc.) for new SPA UI instead of hand-rolling equivalents — the app doesn't use Vuetify, Element Plus, or any other component kit.
- **Tailwind for layout/spacing/utility styling**, Naive UI for interactive components. Don't mix in another CSS framework.
- **Follow existing patterns before inventing new ones** — e.g. table row actions use solid-circle icon buttons (`w-7 h-7 rounded-lg bg-{color} text-white`), copy from `pages/assets/Index.vue` rather than creating a new style.
- **i18n**: wire new user-facing strings through `t('namespace.key')` with entries in both `en.json` and `km.json` — don't hardcode English in new components.
- **Auth/roles**: roles are a plain string column (`users.role`), not a relation. Always enforce authorization server-side (controller/middleware) — the SPA's route guards are UX-only.
- **API routes**: must stay under the `api.` route-name prefix (`routes/api.php` wraps everything in `Route::name('api.')`) — route caching breaks otherwise.
- **Don't build on orphaned code**: `StockTransferController`, `InventoryController`, `AssetReportController`, `Role` model, `AssetAudit` model are not wired into any route. Grep the route files before assuming a controller is reachable.
- **Testing**: `composer test` / `php artisan test` runs against sqlite `:memory:`, independent of the local dev DB.
- **Code style**: run `./vendor/bin/pint` for PHP. No repo-enforced JS/Vue linter is configured in `frontend/package.json` — match existing formatting by hand.
