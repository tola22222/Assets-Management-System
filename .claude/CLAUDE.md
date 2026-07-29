# CLAUDE.md (.claude)

Supplementary guidance for Claude Code in this repo. Read alongside the root [CLAUDE.md](../CLAUDE.md), which has the full architecture writeup — this file is a quick-reference on stack facts and conventions to keep consistent.

## Stack summary

- **Backend**: Laravel 12 / PHP 8.2, in `backend/` (not the repo root — the repo is split into `backend/` and `frontend/` sibling directories). Two route sets share the same models: `backend/routes/web.php` (Blade, session auth) and `backend/routes/api.php` (JSON API, Sanctum bearer tokens, controllers under `app/Http/Controllers/Api/`).
- **Frontend**: Vue 3 SPA in `frontend/`, built with Vite. **UI kit is hand-rolled Tailwind CSS v4** (`@tailwindcss/vite`, theme tokens in a CSS `@theme` block in `frontend/src/assets/main.css` — no `tailwind.config.*`). There is **no Vuetify** — a Vuetify migration existed at one point but was rolled back (commit `a33657d`); don't trust old commit messages or docs that reference it.
- State: Pinia (`stores/auth.js`, `stores/toast.js`). Routing: `vue-router`. i18n: `vue-i18n` (Composition API, `en.json`/`km.json`). Charts: `chart.js` via `vue-chartjs`. HTTP: single axios instance in `api/http.js`.

## Best practices to follow in this repo

- **Change both sides when needed.** Most domain features exist in both the Blade UI and the Vue SPA. Before considering a backend change "done," check if the corresponding web *and* Api controller both need the update.
- **Tailwind + the shared component classes, not a new UI kit.** Reuse the `.btn-*`/`.card`/`.badge-*`/`.input`/`.table-wrap` classes in `frontend/src/assets/main.css` and the shared components in `frontend/src/components/ui/` (`Modal.vue`, `ConfirmDialog.vue`, `PageHeader.vue`, etc.) instead of hand-rolling new equivalents or pulling in a component library (no Vuetify, Naive UI, Element Plus, etc.). Both `backend/` (Blade UI) and `frontend/` (SPA) use Tailwind CSS v4, but via separate configs/builds — they don't share styles.
- **Follow existing patterns before inventing new ones** — e.g. table row actions in the SPA use a row of small square icon `<button>`s (view/edit/delete), not a dropdown menu; copy from `frontend/src/pages/assets/Index.vue` rather than creating a new style.
- **i18n**: wire new user-facing strings through `t('namespace.key')` with entries in both `en.json` and `km.json` — don't hardcode English in new components.
- **Auth/roles**: roles are a plain string column (`users.role`), not a relation. Always enforce authorization server-side (controller/middleware) — the SPA's route guards are UX-only.
- **API routes**: must stay under the `api.` route-name prefix (`backend/routes/api.php` wraps everything in `Route::name('api.')`) — route caching breaks otherwise.
- **Don't build on orphaned code**: `StockTransferController`, `InventoryController`, `AssetReportController`, `Role` model, `AssetAudit` model are not wired into any route. Grep the route files before assuming a controller is reachable.
- **Testing**: `composer test` / `php artisan test` (run from `backend/`) runs against sqlite `:memory:`, independent of the local dev DB.
- **Code style**: run `./vendor/bin/pint` (from `backend/`) for PHP. No repo-enforced JS/Vue linter is configured in `frontend/package.json` — match existing formatting by hand.
