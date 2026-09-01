---
description: Build a full UAT dataset seeder (all roles, all modules) and run a scripted end-to-end test pass over every feature in the PEPY AMS.
---

# UAT Seeder + Full-System Test Pass

Build a **realistic User-Acceptance-Testing dataset** for the PEPY Asset Management System, then drive every feature end to end and report what works, what breaks, and what is unreachable.

Work from `backend/` for PHP and `frontend/` for the SPA. Read `CLAUDE.md` and `.claude/CLAUDE.md` first — they describe the two-frontend split and the conventions this repo enforces.

---

## Part 0 — Ground truth before you write a line

The current seed state is almost empty. Confirm it, don't assume it:

| Source | What it seeds today |
|---|---|
| `database/seeders/DatabaseSeeder.php` | **1 user** (`admin@ams.com` / `password123`, role `operations_hr_manager`) + **4 categories** (MOV, FAF, COM, EQU) |
| migration `2026_07_20_161520_add_code_to_locations_table` | 12 locations (SR office + 11 schools) |
| migration `2026_08_31_120000_add_spean_thnort_site` | 13th location, Spean Thnort HS (`SP`) |
| **everything else** | **empty** — no staff, programs, suppliers, assets, stock, assignments, transfers, verifications, disposals, notifications, activity logs, or settings |

Only one of the four roles has an account, so **three-quarters of the permission surface cannot be tested at all** until this seeder exists. That is the gap you are closing.

Verify first:

```bash
cd backend
php artisan tinker --execute="collect(['users','asset_categories','locations','assets','staff','programs','suppliers','stock_items','asset_assignments','asset_transfers','asset_verifications','asset_disposals','notifications','activity_logs','settings'])->each(fn(\$t) => print(\$t.': '.DB::table(\$t)->count().PHP_EOL));"
```

---

## Part 1 — Build the seeder

Create `database/seeders/UatSeeder.php` (plus focused sub-seeders if it grows past ~300 lines). Register it so `php artisan db:seed --class=UatSeeder` runs it. **Do not** change `DatabaseSeeder` — production runs that on every deploy.

### Hard requirements

1. **Idempotent.** Use `firstOrCreate` / `updateOrCreate` keyed on natural keys (email, asset_code, stock_code, location code). Running it twice must not duplicate or crash.
2. **Never call `Asset::create()` with a hand-typed code.** Use `AssetCodeService::nextCode($locationId, $categoryId)`, or, if you preserve real register codes from `.claude/commands/PEPY_Asset_Inventory_Cleaned.md`, call `AssetCodeService::bumpSequenceIfHigher()` afterwards so the `asset_code_sequences` counter stays ahead. Colliding codes are the one thing this system cannot recover from cleanly.
3. **Guard QR generation.** `AssetCodeService::generateQrCode()` needs the PHP **GD** extension and writes a PNG per asset. Accept a `--with-qr` flag (default **off**) — seeding 900 assets with QR on is slow and hard-fails without GD. Print a clear skip notice when off.
4. **`php artisan storage:link` must have been run**, or every seeded photo/QR URL 404s. Check for `public/storage` and warn loudly if missing.
5. Wrap the whole run in a transaction where practical, and print a summary table of row counts at the end.

### Users — all four roles, plus edge cases

Password `password123` for every account so testers can switch roles fast.

| Email | Role | Notes |
|---|---|---|
| `opm@pepy.test` | `operations_hr_manager` | primary admin |
| `opm2@pepy.test` | `operations_hr_manager` | **required** — transfer approve/reject calls `abort_if(requested_by === Auth::id())`, so a second OPM is the only way to test the approval path |
| `finance@pepy.test` | `finance_manager` | |
| `ed@pepy.test` | `executive_director` | sole disposal approver |
| `staff.sr@pepy.test` | `staff` | linked to a Staff row **with** `location_id` = PEPY Office → tests site scoping |
| `staff.kl@pepy.test` | `staff` | linked to a Staff row with `location_id` = Kralanh HS |
| `staff.nosite@pepy.test` | `staff` | linked to a Staff row with `location_id` **NULL** → tests the fail-open/fail-closed inconsistency (see Part 3, F-01) |
| `locked@pepy.test` | `staff` | `is_locked = true` → login must be refused |
| `inactive@pepy.test` | `staff` | `is_active = false` → login must be refused |

Set `staff_id` on every staff-role user — `DashboardController::staffDashboard()` and `AssetController::index()` both read `user->staff->location_id`, and a staff user with no linked Staff row will render a broken dashboard.

### Reference data

- **Staff** — ~15 rows spread across at least 5 locations, mixed `position`, some `status = inactive`, some with no `email`/`phone` (tests the `—` fallbacks). Every staff user above must have its row here.
- **Programs** — the real ones: Dream Program, LC_English, ICT, Youth Employment, Office.
- **Suppliers** — ~6 rows with name/phone/address.
- **Categories** — the 4 seeded ones, plus one extra with a valid 2–6 char `short_name` (e.g. `TOOL` / "Tools & Hardware") to prove categories are no longer hard-locked to MOV/FAF/COM/EQU.

### Assets — the core of the dataset

Target **~120–200 assets** (enough to exercise search, sort, pagination-free rendering, and the charts without a slow seed). Draw names, prices, dates, serials, and site distribution from `.claude/commands/PEPY_Asset_Inventory_Cleaned.md` so the data reads like the real register.

Deliberately seed each of these states — the UI branches on all of them:

| State | Why |
|---|---|
| `condition = good`, `status = active` | "In use" badge |
| `condition = fair` or `broken` | "Needs repair" badge + `DAMAGE_FLAGGED` path |
| `condition = lost` | "Lost" badge + `/reports/lost` |
| `status = disposed` | "Retiring" badge + `/reports/disposed` |
| `purchase_price = NULL` | dashboard "recorded value / % priced" + `/reports/data-completeness` |
| `purchase_date = NULL` | `notifications:missing-fields` digest |
| `serial_number = NULL` | data-completeness "Serial Number" row |
| all three NULL | worst-case completeness row |
| `location_id` spread over **all 13 sites**, incl. sites with 0 assets | `/stock-items/by-location` renders zero-rows deliberately |
| ≥ 20 assets sharing one `name` | `/reports/by-model` "high" stock-level badge (`Asset::stockLevelFor`) |
| `created_at` back-dated across **the last 12 months and last 30 days** | `/dashboard/by-period` day/month/year buckets are empty otherwise — **the trend chart will look broken without this** |

### Stock (consumables) — must be seeded directly

**This module has no create path in the running app.** There is no `store` route, no `receive` endpoint, and `AssetCodeService::nextStockCode()` is called from nowhere but the test suite. The Stock page can only ever be empty unless the seeder writes the rows. So:

- ~12 `StockItem` rows via `StockItem::create()` with `stock_code` from `AssetCodeService::nextStockCode()`.
- Cover all three computed statuses: **low** (`balance <= min_threshold`), **normal**, **high** (`balance >= max_threshold`). `status` is a computed accessor — set the balances and thresholds, never the status.
- Give some items a `StockTransaction` history (both `in` and `out` rows, `recorded_by` set) so the detail modal has content, and leave at least two items with **no** transactions so the delete path is testable (`destroy` refuses items that have history).
- Spread `location_id` across sites.

### Workflow rows — seed every state of every state machine

| Entity | Seed these states |
|---|---|
| `AssetAssignment` | `active` (to staff), `active` (to program), `returned`, one **overdue** (`due_date` in the past), one with an `image_path` |
| `AssetTransfer` | `pending` **requested by `opm2`** (so `opm@pepy.test` can approve it), `pending` requested by finance, `approved`, `rejected` |
| `AssetVerification` | `condition` good / fair / broken / lost; `quantity_verified != 1` (feeds `notifications:count-discrepancy`); **at least one row with `verified_at` explicitly NULL** — see F-02 below, nothing in the app can produce one |
| `AssetDisposal` | `pending` with `recommended_action` repair / disposal / replacement (so `ed@pepy.test` has something to approve), one `approved`, one `rejected` with `review_notes` |
| `Notification` | unread + read rows for several users, `type` = `qr_scan`, `transfer_request`, `disposal_request`, `asset_flagged`; set a real `url` on some (`/app/reports`) — see F-03 |
| `ActivityLog` | ~40 rows across users and actions so the paginated log has >1 page |
| `Setting` | `organization_name`, `system_name`, `theme_color`, `report_interval_months`, mail keys — enough that `/settings` renders fully populated |

Do **not** seed `AssetReturn` rows as if they were reachable — that module is routed but has no UI (see F-04).

---

## Part 2 — Run the test pass

Seed, then boot both halves and walk the app:

```bash
cd backend  && php artisan migrate:fresh --seed && php artisan db:seed --class=UatSeeder && php artisan serve
cd frontend && npm run dev     # :5173, proxies /api -> :8000
```

Run `php artisan test` too and report failures — CI does **not** run the suite, so a red suite ships to production unnoticed.

### Per-role matrix

For **each** of `operations_hr_manager`, `finance_manager`, `executive_director`, `staff (with site)`, `staff (no site)`, log in and record for every page: does it load, is the data right, and does every button do what its label says?

Pages: Dashboard · Assets · Assets/Import · Categories · Locations · Assignments · Transfers · Verifications · Disposals · Stock · Programs · Staff · Suppliers · Users · Settings · Activity Logs · Reports · QR Scan · Search · Notifications · Profile.

### What to check on every page

1. **Empty state** — does it read as "nothing here yet" rather than a crash or a silent zero?
2. **Every button** — fires the right request, shows a toast, and refreshes the list. Flag any button that 403s for the role that can see it.
3. **Every link** — `RouterLink`s resolve; nothing dead-ends.
4. **Role gating** — a hidden button must also be refused server-side (`curl` the endpoint with that role's token to prove it).
5. **Both locales** — switch to Khmer and confirm no raw English leaks through (the i18n files are 809/809 complete, so any English you see is a hardcoded string, not a missing key).
6. **Both themes** — light and dark.

### Known-issue regression list

These are already-identified defects. Confirm each still reproduces, or note it as fixed:

- **F-01** `AssetController::index` filters staff by `where('location_id', $staffLocationId)`; Laravel turns a NULL value into `whereNull`, so a staff user with **no site sees only unplaced assets** (i.e. nothing). `AssetVerificationController::index` and `QrScanController` in the same situation fail **open** and show everything. Two documented-opposite behaviours for the same user.
- **F-02** `AssetVerificationController::store` and `QrScanController::verify` both set `verified_at = now()`. Every verification is born "Complete", so the **"Mark complete" button can never render**, the "Pending" badge is unreachable, and `POST /asset-verifications/{id}/complete` is dead. `staffDashboard.upcoming_verifications` is permanently 0.
- **F-03** Notifications carry a `url`, and the four scheduled commands set it to `/app/reports` — but `pages/notifications/Index.vue` never renders it. No notification is clickable.
- **F-04** `AssetReturn` is fully routed (`index/store/approve/reject`) with no UI anywhere. `staffDashboard.pending_returns` counts `AssetReturn` rows, so that KPI is permanently 0.
- **F-05** The Stock page has no create/receive control at all (see Part 1).
- **F-06** `/search` and `/qr-scan` have **no link anywhere in the app** — no sidebar entry, no header search box. Reachable by typed URL only.
- **F-07** Dashboard's "Add Asset" button routes to `/assets` instead of opening the create modal.
- **F-08** `role.replace('_', ' ')` in `AppLayout.vue` and `profile/Index.vue` replaces only the first underscore → renders "operations hr_manager".
- **F-09** Verifications page shows the "Mark complete" button to every role, but the route is `role:operations_hr_manager` — it would 403 for Finance/ED/Staff if F-02 ever let it render.
- **F-10** Stock status badges render the raw English `low` / `normal` / `high` instead of the existing `stock.low` / `.normal` / `.high` i18n keys.
- **F-11** Search results are plain `<div>`s — no link to the matched entity.
- **F-12** `User::getPhotoUrlAttribute()` falls back to `ui-avatars.com`, sending every user's real name to a third-party service on page load.

---

## Part 3 — Report

Produce a single report with:

1. **Seeder summary** — final row counts per table, and the login table for testers.
2. **Feature matrix** — one row per page × role: Pass / Fail / Blocked, with the failing request and status code.
3. **New defects** — anything not already in the F-list, with reproduction steps.
4. **Regression status** — F-01 … F-12, each confirmed or fixed.
5. **Spec conformance** — check the register, tag scheme, required fields, and reporting against `.claude/commands/PEPY_Asset_Management_System.md`, and the count/reconciliation/disposal workflow against `.claude/commands/PEPY_Asset_Checking_Counting_Manual_Guideline.md`.

Do not fix defects while testing unless asked — record them, finish the pass, then propose an ordered fix list.
