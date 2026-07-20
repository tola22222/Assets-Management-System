# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

An asset-management system (PEPY) built on **Laravel 12 / PHP 8.2**. It has **two coexisting frontends** backed by the same models and business logic:

1. **Legacy Blade UI** — server-rendered pages in `resources/views/`, driven by `routes/web.php` and the controllers in `app/Http/Controllers/` (root namespace). Session/cookie auth.
2. **Vue 3 SPA** — a standalone app in `frontend/`, driven by `routes/api.php` and the mirror controllers in `app/Http/Controllers/Api/`. Talks to the backend as a JSON API using Sanctum **bearer tokens** (stored in `localStorage`, sent via `Authorization: Bearer`).

Most domain features exist in both. When changing behavior, check whether the corresponding **web** and **Api** controller both need the change.

## Commands

### Backend (Laravel, run from repo root)
- `composer dev` — runs server + queue listener + `pail` logs + Vite (Blade assets) concurrently. This is the normal local dev loop for the Blade UI.
- `php artisan serve` — API/backend only on `http://127.0.0.1:8000` (what the SPA proxies to).
- `composer test` or `php artisan test` — runs the PHPUnit suite (clears config first). Tests use **sqlite `:memory:`** (see `phpunit.xml`), not the MySQL dev DB.
- Run one test: `php artisan test --filter=SomeTest` or `php artisan test tests/Feature/SomeTest.php`.
- `./vendor/bin/pint` — code style (Laravel Pint). CI style config is `.styleci.yml`.
- `php artisan migrate` / `php artisan db:seed` — schema and seed data. Dev DB is MySQL (`DB_DATABASE` in `.env`).

### Vue SPA (run from `frontend/`)
- `npm run dev` — Vite dev server on `:5173` with HMR, proxies `/api` → `http://127.0.0.1:8000` (see `frontend/vite.config.js`). Run `php artisan serve` alongside it. Use this while actively developing the SPA.
- `npm run build` and `npm run build:local` (mode `artisan`) — both build to `frontend/dist/` with base `/app/` (unified in commit `657c19d` after a prod white-screen bug: with base `/`, the built HTML requested assets at the domain root instead of `/app/assets/*`, 404ing wherever the app is actually mounted at `/app`). **Do not reintroduce a `/` base for the default build.** In production this is copied to `public/app/` (see Dockerfile) and served by nginx — both the default host (via Laravel's `spa` route) and `app.pepyasset.online` reach it at `/app/`.
- After `npm run build:local`, just `php artisan serve` and open `http://localhost:8000` — it redirects to `/app` and Laravel serves the built SPA (no Vite process, but no HMR either; rebuild to see changes). This is the **single-server dev** option.

### Local single-server serving (how `php artisan serve` shows the SPA)
`routes/web.php` has a `spa` route (`/app/{path?}`) that serves the built SPA from `frontend/dist` (falling back to `public/app`), and `/` redirects to `/app` (keeping the `dashboard` route name). The build deliberately stays **out of `public/`**: a real `public/app` directory makes PHP's built-in server (`artisan serve`) strip `/app` from deep-link paths (so `/app/login` would hit the Blade `/login` route instead of the SPA). Serving through the Laravel route avoids that. The legacy Blade dashboard moved from `/` to `/blade`.

Note: there are **two `package.json` / Vite setups**. The root one builds Blade assets (`resources/`); `frontend/` builds the Vue SPA. They are independent.

## Architecture notes that aren't obvious from a single file

### Route naming collision
`routes/api.php` wraps everything in `Route::name('api.')->group(...)`. This is deliberate: web and API define overlapping resources (e.g. both have an `assets` resource), and Laravel route names must be globally unique or `php artisan route:cache` fails to build (this broke a deploy — see commit `c0e3f93`). **Any new API route must keep the `api.` prefix.**

### Auth & roles
- API auth is Sanctum (`auth:sanctum` middleware, bearer tokens). Web auth is session-based with a `guest`/auth middleware split.
- **Roles are a plain string column on `users.role`**, not a relation — the `Role` model is effectively unused. The four roles match the Asset Checking & Counting Manual exactly: `operations_hr_manager`, `finance_manager`, `executive_director`, `staff`. Check roles via `User` helpers: `isOperationsHrManager()`, `isStaff()`, `isExecutiveDirector()`, `isFinanceManager()`, and derived gates like `canApproveDisposal()` (operations/HR manager or executive director). Route-level checks use `role:operations_hr_manager` etc. via `RoleMiddleware`.
- The SPA enforces `requiresAuth` / `guest` / `adminOnly` via route meta in `frontend/src/router/index.js` and a `beforeEach` guard reading `localStorage`. This is UX-level only — real authorization must be enforced server-side in the controllers.

### Asset codes & QR (`app/Services/AssetCodeService.php`)
- Asset codes follow the Asset Checking & Counting Manual's scheme: `PEY-[SITE]-[CATEGORY]-[####]` (e.g. `PEY-SR-FAF-0928`). `SITE` is the `code` column on `locations` (seeded for the 13 real PEPY sites — office `SR` + 12 partner schools); `CATEGORY` is one of `MOV`/`FAF`/`COM`/`EQU` (the `AssetCategory.short_name`). `AssetCodeService::nextCode(?int $locationId, int $categoryId)` throws `InvalidArgumentException` for an unrecognized category or a location without a site code.
- The numeric sequence is **global per category** — not per site, not per year — and never reused: it's tracked in the `asset_code_sequences` table (one row per category code) and incremented inside a `DB::transaction` with `lockForUpdate()`, so concurrent "add asset" requests can't collide. A `2026_07_20_..._backfill_asset_codes_to_pey_format` migration renamed any pre-existing non-conforming codes into this scheme.
- Both the `Asset` create/update controllers (web + Api) and the bulk import services (`AssetImportService`, the legacy CSV `AssetImportController`) require `location_id` and resolve it before generating a code — assets always belong to a location now, not just an `AssetStock` line.
- Each asset gets a PNG QR code (stored on the `public` disk at `qrcodes/{code}.png`) that encodes the **public** asset URL (`asset.public.show` route). `routes/web.php` exposes public, unauthenticated `/asset/{assetCode}` view + condition-update endpoints — these are how scanned QR codes let anyone report an asset's condition.

### Domain workflow modules
The core entities are `Asset`, `AssetCategory`, `Location`, `AssetStock`, plus workflow entities that carry approve/reject/complete state transitions: `AssetAssignment`, `AssetTransfer`, `AssetReturn`, `AssetVerification`, `AssetDisposal`, `AssetMovement`. Supporting: `Program`, `Staff`, `Supplier`, `Notification`, `ActivityLog`, `Setting`, `Report`. Workflow actions are custom POST routes on top of the resource routes (e.g. `asset-transfers/{id}/approve`), present in both `web.php` and `api.php`.

### SPA structure (`frontend/src/`)
- `api/http.js` — the single axios instance (baseURL `/api`, injects bearer token, redirects to login on 401).
- `stores/` — Pinia (`auth.js`, `toast.js`).
- `pages/<module>/Index.vue` — one folder per domain module, mostly CRUD index pages driven by the `useApiCrud.js` composable.
- `i18n/` — vue-i18n (Composition API mode, `legacy: false`) with `en.json` and `km.json` (Khmer). Components call `useI18n()` and `t('namespace.key')`; the font is **Kantumruy Pro** (loaded in `index.html`, set as `--font-sans`/`--font-display` in `main.css`) because it covers both Latin and Khmer glyphs in one family. Not every string in the app is wired through `t()` yet — when touching a page, check whether its labels are still hardcoded English before assuming translation "just works".
- `layouts/` — `AppLayout` (authed shell) vs `AuthLayout` (login).
- `composables/useTheme.js` — shared dark/light state (persisted to `localStorage`); the initial `<html class="dark">` is set by a no-flash inline script in `index.html` before Vue mounts, so `useTheme` only mirrors/toggles it, it doesn't own the first paint.
- `composables/useLocale.js` — wraps vue-i18n's reactive `i18n.global.locale`, persists the choice to `localStorage`, and syncs `<html lang>`. This is what makes the Settings "Language" picker actually change the running app instead of just saving a DB value.
- `composables/useThemeColor.js` — the Settings "Theme Color" picker converts a chosen hex to an HSL-derived shade scale and overrides the `--color-brand-*` CSS custom properties on `<html>` at runtime. This works because Tailwind v4's `@theme` block (`main.css`) compiles those into real CSS variables that every `bg-brand-*`/`text-brand-*` utility already references — no rebuild needed. Persisted to `localStorage` and re-applied on boot via a side-effect import in `main.js`.
- Table row actions (Edit/Delete/Approve/Reject/etc.) across CRUD pages use a consistent solid-circle icon-button style (`w-7 h-7 rounded-lg bg-{color} text-white`, `w-4 h-4` icon at `stroke-width: 2.2`) rather than text links — copy the pattern from `pages/assets/Index.vue` rather than inventing a new one.

### Bulk asset import (`app/Services/AssetImportService.php`)
Imports the fixed-asset register from `.xlsx`/`.xls`/CSV (via `AssetImportController`, PhpSpreadsheet). It supports two column layouts autodetected from the header row: the real PEPY workbook (preserves existing asset codes like `PEY-SR-FAF-0928` and derives category from the code's category segment via `CATEGORY_NAMES`) and a simpler template (auto-generates codes, matches category by name). Rows are upserted by asset code, so re-importing the same file is safe. It always reads the workbook's **first sheet by index** (`getSheet(0)`), not whichever tab was active when the file was last saved — the real PEPY register is a multi-tab workbook (full list, per-program subsets, summary sheets), so `getActiveSheet()` would be non-deterministic.

### Profile & Backup (Settings)
- `Api\AuthController::updateProfile`/`changePassword` (routes `/profile`, `/profile/password`) let any authenticated user edit their own name/phone/photo and password — separate from the admin-only `/users` CRUD.
- `Api\SettingController` backup/restore endpoints (`/settings/backup`, `/settings/backups`, download/restore/delete) mirror the legacy Blade `SettingController`'s behavior: they copy `database/database.sqlite` to `storage/app/backups/`. This is only meaningful for local sqlite dev/testing — the deployed DB is MySQL, so these endpoints don't back up production data as-is.

## Deployment
Pushing to `main` triggers `.github/workflows/deploy.yml`: builds the multi-stage `Dockerfile` (Vue build → PHP-FPM Alpine image with the SPA baked into `public/app/`), pushes to Docker Hub, then SSHes into an EC2 host and `docker compose up`s app + nginx + MySQL. The deploy script writes `docker-compose.yml` and `nginx/default.conf` inline on the server, waits for MySQL, then runs `migrate --force` and re-caches config/route/view.

nginx (`nginx/default.conf`) serves the Blade app at the default host and the **Vue SPA at `app.pepyasset.online`**: `/api/` → Laravel, everything else → `public/app/index.html` with history-mode fallback.

The container `entrypoint.sh` fixes storage perms, waits for the DB, and runs a background loop calling `php artisan schedule:run` every 60s (there is no system cron).
