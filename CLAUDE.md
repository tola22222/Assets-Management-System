# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

An asset-management system (PEPY) built on **Laravel 12 / PHP 8.2**, with **two coexisting frontends** backed by the same models and business logic:

1. **Legacy Blade UI** — server-rendered pages in `backend/resources/views/`, driven by `backend/routes/web.php` and the controllers in `backend/app/Http/Controllers/` (root namespace). Session/cookie auth.
2. **Vue 3 SPA** — a standalone app in `frontend/`, driven by `backend/routes/api.php` and the mirror controllers in `backend/app/Http/Controllers/Api/`. Talks to the backend as a JSON API using Sanctum **bearer tokens** (stored in `localStorage`, sent via `Authorization: Bearer`).

Most domain features exist in both. When changing behavior, check whether the corresponding **web** and **Api** controller both need the change.

## Repository layout

```text
Assets-Management-System/
├── backend/     — Laravel 12 app (API + legacy Blade UI). Everything below is relative to here.
├── frontend/    — Vue 3 SPA (Vite, Pinia, vue-router, vue-i18n, Tailwind)
├── docker/      — entrypoint.sh baked into the runtime image
├── nginx/       — local docker-compose nginx config (prod's is written inline by the deploy workflow, see Deployment)
├── Dockerfile   — multi-stage build: frontend/ built first, then copied into the backend/ PHP-FPM image
└── docker-compose.yml — local-only compose stack (app + nginx + MySQL); NOT what runs in production
```

`backend/` and `frontend/` are two independent packages — separate `composer.json`/`package.json`, separate dependency trees, separate git-ignored `vendor/`/`node_modules/`. Nothing in `frontend/` depends on Laravel being co-located; the only cross-package coupling is the `spa` route in `backend/routes/web.php`, which reads the sibling `frontend/dist/` build output at runtime (see below).

## Commands

### Backend (Laravel, run from `backend/`)
- `composer dev` — runs server + queue listener + `pail` logs + Vite (Blade assets) concurrently. This is the normal local dev loop for the Blade UI.
- `php artisan serve` — API/backend only on `http://127.0.0.1:8000` (what the SPA proxies to).
- `composer test` or `php artisan test` — runs the PHPUnit suite (clears config first). Tests use **sqlite `:memory:`** (see `backend/phpunit.xml`), not the MySQL dev DB.
- Run one test: `php artisan test --filter=SomeTest` or `php artisan test tests/Feature/SomeTest.php`.
- `./vendor/bin/pint` — code style (Laravel Pint). CI style config is `.styleci.yml` at the repo root.
- `php artisan migrate` / `php artisan db:seed` — schema and seed data. Local dev DB defaults to **sqlite** (`backend/database/database.sqlite`, per `.env.example`'s `DB_CONNECTION=sqlite`) — not MySQL as you might expect from the deployment setup; production (`docker-compose.yml`, written inline by the deploy workflow) runs MySQL 8. Check `backend/.env`'s actual `DB_CONNECTION` before assuming either.

### Vue SPA (run from `frontend/`)
- `npm run dev` — Vite dev server on `:5173` with HMR, proxies `/api` → `http://127.0.0.1:8000` (see `frontend/vite.config.js`). Run `php artisan serve` (from `backend/`) alongside it. Use this while actively developing the SPA.
- `npm run build` and `npm run build:local` (mode `artisan`) — both build to `frontend/dist/` with base `/app/` (unified in commit `657c19d` after a prod white-screen bug: with base `/`, the built HTML requested assets at the domain root instead of `/app/assets/*`, 404ing wherever the app is actually mounted at `/app`). **Do not reintroduce a `/` base for the default build.** In production this is copied to `backend/public/app/` (see Dockerfile) and served by nginx — both the default host (via Laravel's `spa` route) and `app.pepyasset.online` reach it at `/app/`.
- After `npm run build:local`, run `php artisan serve` from `backend/` and open `http://localhost:8000` — it redirects to `/app` and Laravel serves the built SPA (no Vite process, but no HMR either; rebuild to see changes). This is the **single-server dev** option.

### Local single-server serving (how `php artisan serve` shows the SPA)
`backend/routes/web.php` has a `spa` route (`/app/{path?}`) that serves the built SPA from the **sibling** `../frontend/dist` (resolved via `dirname(base_path()).'/frontend/dist'`, falling back to `backend/public/app`), and `/` redirects to `/app` (keeping the `dashboard` route name). The build deliberately stays **out of `backend/public/`** for local dev: a real `public/app` directory makes PHP's built-in server (`artisan serve`) strip `/app` from deep-link paths (so `/app/login` would hit the Blade `/login` route instead of the SPA). Serving through the Laravel route avoids that. In the Docker image, `frontend/dist` isn't present as a sibling (the two packages are pulled apart at build time — see Deployment), so the route falls back to `public/app`, which is exactly where the Dockerfile bakes the build; production nginx serves those static files directly before Laravel is even reached. The legacy Blade dashboard moved from `/` to `/blade`.

Note: there are **two `package.json` / Vite setups**, both inside `backend/`. `backend/package.json` + `backend/vite.config.js` build Blade assets (`backend/resources/`); `frontend/package.json` + `frontend/vite.config.js` build the Vue SPA. They are independent.

## Architecture notes that aren't obvious from a single file

### Route naming collision
`backend/routes/api.php` wraps everything in `Route::name('api.')->group(...)`. This is deliberate: web and API define overlapping resources (e.g. both have an `assets` resource), and Laravel route names must be globally unique or `php artisan route:cache` fails to build (this broke a deploy — see commit `c0e3f93`). **Any new API route must keep the `api.` prefix.**

### Auth & roles
- API auth is Sanctum (`auth:sanctum` middleware, bearer tokens). Web auth is session-based with a `guest`/auth middleware split.
- **Roles are a plain string column on `users.role`**, not a relation — the `Role` model is effectively unused. The four roles match the Asset Checking & Counting Manual exactly: `operations_hr_manager`, `finance_manager`, `executive_director`, `staff`. Check roles via `User` helpers: `isOperationsHrManager()`, `isStaff()`, `isExecutiveDirector()`, `isFinanceManager()`, and derived gates like `canApproveDisposal()` (operations/HR manager or executive director). Route-level checks use `role:operations_hr_manager` etc. via `RoleMiddleware`.
- The SPA enforces `requiresAuth` / `guest` / `adminOnly` via route meta in `frontend/src/router/index.js` and a `beforeEach` guard reading `localStorage`. This is UX-level only — real authorization must be enforced server-side in the controllers.

### Asset codes & QR (`backend/app/Services/AssetCodeService.php`)
- Asset codes follow the Asset Checking & Counting Manual's scheme: `PEY-[SITE]-[CATEGORY]-[####]` (e.g. `PEY-SR-FAF-0928`). `SITE` is the `code` column on `locations` (seeded for the 13 real PEPY sites — office `SR` + 12 partner schools); `CATEGORY` is the `AssetCategory.short_name` (2-6 alphanumeric chars). `MOV`/`FAF`/`COM`/`EQU` are the four categories the manual originally shipped with and remain the suggested defaults, but categories are **no longer hard-locked to that list** — any category with a well-formed `short_name` can generate codes. `AssetCodeService::nextCode(?int $locationId, int $categoryId)` throws `InvalidArgumentException` for a malformed/missing category code or a location without a site code.
- The numeric sequence is **global per category** — not per site, not per year — and never reused: it's tracked in the `asset_code_sequences` table (one row per category code) and incremented inside a `DB::transaction` with `lockForUpdate()`, so concurrent "add asset" requests can't collide. Bulk import preserves existing printed-tag codes (doesn't call `nextCode()`), so it calls `AssetCodeService::bumpSequenceIfHigher()` to keep the counter ahead of every imported code and avoid future collisions.
- Both the `Asset` create/update controllers (web + Api) and the bulk import services (`AssetImportService`, the legacy CSV `AssetImportController`) require `location_id` and resolve it before generating a code — assets always belong to a location now.
- Each asset gets a PNG QR code (stored on the `public` disk at `qrcodes/{code}.png`) that encodes the **public** asset URL (`asset.public.show` route). `backend/routes/web.php` exposes public, unauthenticated `/asset/{assetCode}` view + condition-update endpoints — these are how scanned QR codes let anyone report an asset's condition.

### Domain workflow modules
The core entities are `Asset`, `AssetCategory`, `Location`, plus workflow entities that carry approve/reject/complete state transitions: `AssetAssignment`, `AssetTransfer`, `AssetReturn`, `AssetVerification`, `AssetDisposal`, `AssetMovement`. Supporting: `Program`, `Staff`, `Supplier`, `Notification`, `ActivityLog`, `Setting`, `Report`. Workflow actions are custom POST routes on top of the resource routes (e.g. `asset-transfers/{id}/approve`), present in both `web.php` and `api.php`. Assets are received into the register via the standard Asset create endpoints (web + Api) or bulk import — there is no separate "receive stock" workflow (a bulk "Receive Assets" feature at `asset-stocks`, which created N individual `Asset`+`AssetMovement` rows per receipt, was removed). The `AssetStock` model (a `asset_id`/`location_id`/`quantity` record) and its migration still exist but have no controller — same orphaned-code caveat as below.

### Orphaned legacy code — not wired into any route
`StockTransferController` (+ its `StockTransfer`/`StockTransferItem` models), `InventoryController`, and `AssetReportController` exist under `backend/app/Http/Controllers/` but have **no entries in `backend/routes/web.php` or `backend/routes/api.php`** — they predate the current `AssetTransfer`/`AssetMovement` workflow and were superseded but never deleted. Likewise the `Role` model (roles are a `users.role` string, see above) and `AssetAudit` model have no live callers. Don't assume a match on these names means the feature is reachable — grep the route files to confirm before building on top of them.

### SPA structure (`frontend/src/`)
- **UI kit is hand-rolled Tailwind CSS v4** (`@tailwindcss/vite` plugin, no `tailwind.config.*` — theme tokens live in a CSS `@theme` block). There is **no Vuetify** in this codebase (a Vuetify migration existed at one point but was rolled back — see commit `a33657d` — don't trust older docs/commit messages that mention it). `frontend/src/assets/main.css` defines the PEPY brand palette (`--color-brand-*`, `--color-accent-*`) plus light/dark semantic tokens (flipped under a `.dark` class on `<html>`) and reusable component classes under `@layer components`: `.btn-primary`/`.btn-accent`/`.btn-ghost`/`.btn-danger`/etc., `.card`, `.input`/`.select`, `.badge-*`, `.table-wrap`/`.data-table`, `.modal-panel`/`.overlay`. Reuse these classes and the shared components in `components/ui/` (`Modal.vue`, `ConfirmDialog.vue`, `PageHeader.vue`, `SearchInput.vue`, `StatusBadge.vue`, `StatCard.vue`, `TableSortIcon.vue`, `TrendChart.vue`/`DonutChart.vue`, etc.) rather than introducing a component kit.
- `frontend/src/composables/useThemeColor.js` — the Settings "Theme Color" picker converts a chosen hex to an HSL-derived shade scale and overwrites the `--color-brand-*` CSS custom properties on `document.documentElement` at runtime (Tailwind v4 compiles `@theme` vars to real custom properties, so every `bg-brand-*`/`text-brand-*` utility already in the DOM restyles instantly, no rebuild needed), persisted to `localStorage` and re-applied on boot via a side-effect import in `main.js`.
- `api/http.js` — the single axios instance (baseURL `/api`, injects bearer token, redirects to login on 401).
- `stores/` — Pinia (`auth.js`, `toast.js`).
- `pages/<module>/Index.vue` — one folder per domain module, mostly CRUD index pages driven by the `useApiCrud.js` composable (fetch/create/update/destroy against a REST endpoint) plus `useTableSearch.js`, `useTableFilter.js`, `useTableSort.js` for client-side table behavior, and `ConfirmDialog.vue` for delete confirmations.
- `i18n/` — vue-i18n (Composition API mode, `legacy: false`) with `en.json` and `km.json` (Khmer). Components call `useI18n()` and `t('namespace.key')`; the font is **Kantumruy Pro** (loaded via Google Fonts in `index.html`) because it covers both Latin and Khmer glyphs in one family. Not every string in the app is wired through `t()` yet — when touching a page, check whether its labels are still hardcoded English before assuming translation "just works".
- `layouts/` — `AppLayout.vue` (authed shell — sidebar nav + header, all in one file, no separate `AppHeader`/`AppNavigationDrawer` components) vs `AuthLayout.vue` (login).
- `composables/useTheme.js` — shared dark/light state (persisted to `localStorage`); the initial `<html class="dark">` is set by a no-flash inline script in `index.html` before Vue mounts, so `useTheme` only mirrors/toggles it, it doesn't own the first paint.
- `composables/useLocale.js` — wraps vue-i18n's reactive `i18n.global.locale`, persists the choice to `localStorage`, and syncs `<html lang>`. This is what makes the Settings "Language" picker actually change the running app instead of just saving a DB value.
- Table row actions (View/Edit/Delete/Approve/Reject/etc.) across CRUD pages use a row of small square icon `<button>`s (e.g. amber "view", brand-colored "edit", red "delete", each `w-7 h-7 rounded-lg`) rather than a dropdown menu — copy the pattern from `pages/assets/Index.vue` rather than inventing a new one.

### Bulk asset import (`backend/app/Services/AssetImportService.php`)
Imports the fixed-asset register from `.xlsx`/`.xls`/CSV (via `AssetImportController`, PhpSpreadsheet). It supports two column layouts autodetected from the header row: the real PEPY workbook (preserves existing asset codes like `PEY-SR-FAF-0928` and derives category from the code's category segment via `CATEGORY_NAMES`) and a simpler template (auto-generates codes, matches category by name). Rows are upserted by asset code, so re-importing the same file is safe. It always reads the workbook's **first sheet by index** (`getSheet(0)`), not whichever tab was active when the file was last saved — the real PEPY register is a multi-tab workbook (full list, per-program subsets, summary sheets), so `getActiveSheet()` would be non-deterministic.

### Dashboard time-series reporting
`Api\DashboardController::byPeriod` (`GET /api/dashboard/by-period?period=day|month|year`, admin-only — `abort_if($request->user()->isStaff(), 403)`) counts assets by `created_at`, bucketed in **PHP via Carbon**, not a raw SQL date-format function — dev is sqlite and prod is MySQL and their date functions differ. Empty buckets in range are pre-filled to 0 so the chart has no gaps. Rendered by `Dashboard.vue`'s "Assets registered over time" card via `TrendChart.vue` (a single-series `vue-chartjs` bar chart, same setup as `DonutChart.vue`) — follow the `dataviz` skill's guidance for any new chart in this app. `DashboardController::index` returns a **different JSON shape for staff vs admin roles** (`staffDashboard()` vs `adminDashboard()`) — any change to the dashboard payload needs a matching branch in `Dashboard.vue`'s template, or a staff login will hit an undefined-field render crash.

### Profile & Backup (Settings)
- `Api\AuthController::updateProfile`/`changePassword` (routes `/profile`, `/profile/password`) let any authenticated user edit their own name/phone/photo and password — separate from the admin-only `/users` CRUD.
- `Api\SettingController` backup/restore endpoints (`/settings/backup`, `/settings/backups`, download/restore/delete) mirror the legacy Blade `SettingController`'s behavior: they copy `database/database.sqlite` to `storage/app/backups/`. This is only meaningful for local sqlite dev/testing — the deployed DB is MySQL, so these endpoints don't back up production data as-is.

## Deployment
Pushing to `main` triggers `.github/workflows/deploy.yml`: builds the multi-stage `Dockerfile` (context is the repo root — first stage builds `frontend/` with Node, second stage installs `backend/`'s composer deps and copies `backend/` into a PHP-FPM Alpine image, then copies the frontend build into `public/app/`), pushes to Docker Hub, then SSHes into an EC2 host and `docker compose up`s app + nginx + MySQL. The deploy script writes `docker-compose.yml` and `nginx/default.conf` inline on the server (both reference only container-internal paths, so they didn't need to change for the `backend/`/`frontend/` split), waits for MySQL, then runs `migrate --force` and re-caches config/route/view.

nginx (`nginx/default.conf`) serves the Blade app at the default host and the **Vue SPA at `app.pepyasset.online`**: `/api/` → Laravel, everything else → `public/app/index.html` with history-mode fallback.

The container `entrypoint.sh` (`docker/entrypoint.sh`, stays at repo root — it's infra, not part of either package) fixes storage perms, waits for the DB, and runs a background loop calling `php artisan schedule:run` every 60s (there is no system cron).
