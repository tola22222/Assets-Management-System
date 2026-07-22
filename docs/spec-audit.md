# PEPY Asset & Stock Management — Spec Audit & Test Plan

Audit of the current Laravel 12 implementation against the full PEPY spec (roles, lifecycle,
notifications, audit matrix). Findings are grounded in the actual code — file:line references
throughout. Status legend: ✅ matches spec · ⚠️ partial/gap · ❌ missing/violated.

---

## 1. Role × Action audit matrix — Assets

Roles: **OPM** (operations_hr_manager) · **F&A** (finance_manager) · **ED** (executive_director) · **S** (staff)

| Action | OPM | F&A | ED | S | Spec says | Actual (before fix) | Status |
|---|---|---|---|---|---|---|---|
| Create asset | Allowed | Denied | Denied | Denied | role-scoped | **Any authenticated user** — no role middleware on `assets`/`assets-registeration` store (`routes/api.php:47`, `routes/web.php:101`) | ❌ |
| Edit asset | Allowed | Denied | Denied | Denied | role-scoped | Same as above — any authenticated user | ❌ |
| Delete asset | Denied (all) | Denied | Denied | Denied | **Denied for ALL roles** — retirement is the only removal path | `AssetController@destroy` reachable by any authenticated user, both web and API (`app/Http/Controllers/AssetController.php:145`, `app/Http/Controllers/Api/AssetController.php:88`) | ❌ |
| Change status | Allowed | Conditional | Conditional | Denied | direct edit restricted | Bundled into the open `update` route — any role | ❌ |
| Flag damage/loss | Allowed | Allowed | Allowed | Allowed | all roles can flag | `Api/AssetController::flagIssue` open to any authenticated user | ✅ |
| Submit disposal request | Allowed | Allowed | Denied | Denied | OPM/site staff initiate | `AssetDisposalController::store` open to **any** authenticated user incl. staff | ⚠️ (too permissive — ED can also submit) |
| Approve/reject disposal | Allowed | Denied | Allowed | Denied | ED approves (OPM per manual as backup) | `role:operations_hr_manager,executive_director` (`routes/web.php:145-148`) — matches `canApproveDisposal()` | ✅ |
| View register (all sites) | Allowed | Allowed | Allowed | Denied | OPM/F&A/ED see all | No scoping found — every role sees the full register via `AssetController@index` | ⚠️ (staff should be own-site only) |
| View register (own site) | — | — | — | Allowed | staff limited to own site | Not enforced — staff sees all sites | ❌ |
| Export CSV/PDF | Allowed | Allowed | Allowed | Denied | admin-ish roles only | No role gate on export routes | ⚠️ |
| Generate/assign asset tag | System-only | System-only | System-only | System-only | server-generated only | ✅ confirmed — `asset_code` never in validation rules, always `AssetCodeService::nextCode()` | ✅ |
| Bulk edit/import | Allowed | Denied | Denied | Denied | OPM only | No role gate found on import routes | ❌ |
| Manage locations/sites | Allowed | Denied | Denied | Denied | OPM only | No role gate — `routes/web.php:113`, `routes/api.php:50` | ❌ |
| Manage user accounts/roles | Allowed | Denied | Denied | Denied | OPM only | `role:operations_hr_manager` (`routes/web.php:161-165`) | ✅ |

## 2. Role × Action audit matrix — Stock

The spec's stock model (min/max thresholds, `current_balance`, stock-in/out transactions) **does not
exist in this codebase.** `AssetStock` (`app/Models/AssetStock.php`) is a legacy `quantity` counter
that the actual "Receive Assets" flow bypasses entirely — every unit received becomes its own `Asset`
row via `AssetMovement` (`movement_type = 'stock_in'`), one row per unit, no `stock_out` movement type
exists anywhere. There is no consumable-stock model at all today, so most of this matrix is currently
**N/A pending a product decision** (see Open Decisions §7).

| Action | OPM | F&A | ED | S | Actual | Status |
|---|---|---|---|---|---|---|
| Record Stock-In (= Receive Assets) | Allowed | Allowed | Allowed | Allowed | Any authenticated user, no role gate (`routes/web.php:114-116`) | ⚠️ |
| Record Stock-Out | — | — | — | — | **Does not exist** — no stock-out concept | ❌ N/A |
| Void/reverse a transaction | Offset-only (spec) | — | — | — | `AssetStockController::destroy` **hard-deletes** the `AssetMovement` row, open to any authenticated user (`app/Http/Controllers/AssetStockController.php:93-98`, `routes/web.php:116`) | ❌ (violates append-only requirement, Part F) |
| Edit current_balance directly | Denied (all) | Denied | Denied | Denied | N/A — no `current_balance` field exists | ✅ (vacuously) |
| Set/change thresholds | Allowed | Denied | Denied | Denied | N/A — no threshold fields exist | ❌ N/A |
| Flag stock item damaged/lost | Allowed | Allowed | Allowed | Allowed | Handled via `Asset.condition`, same as asset-level flagging | ✅ |
| View transaction history | Allowed | Allowed | Allowed | Own only | No scoping on `AssetMovement`/`AssetStock` index | ⚠️ |

## 3. Findings requiring immediate fix (P0 — permission bypass / data integrity)

1. **Asset delete is open to every authenticated role**, including `staff` — direct violation of "no
   role may delete an asset; retirement is the only removal path." (`AssetController@destroy` both
   web+API)
2. **Asset create/update has no role restriction** — any authenticated user, including `staff`, can
   register or edit assets.
3. **Location/site management has no role restriction.**
4. **`AssetMovement` (stock-in) records can be hard-deleted by any authenticated user** — violates the
   append-only transaction log requirement (Part F) and removes audit trail.
5. **`AssetVerification::complete()` has no role gate** — anyone with route access can mark a
   verification "complete"; the spec's dual sign-off (Finance Manager + OPM) is entirely unimplemented.
6. **Disposal `store()` allows every role including Executive Director** to submit a disposal request,
   which is unusual (ED should be the approver, not also the requester) — worth confirming intent.

These are fixed in this change; see §6 below for exactly what was changed and what was intentionally
left alone pending a product decision.

---

## 4. Lifecycle stage audit (Part E)

| Stage | Spec requirement | Actual | Status |
|---|---|---|---|
| 1. Preparation | Tag affixed immediately, no untagged asset can exist | ✅ enforced — `asset_code` always server-generated at create time, `unique` constraint | ✅ |
| 2. Inspection | Count by location, tag/ID verification, condition+location recorded | `AssetVerification` records `condition`+`location_id`, but there's no "count session" grouping records by site/date | ⚠️ |
| 3. Reconciliation | Classify Confirmed/Relocated/Missing/New/Incorrect; Feb/Aug tracked as separate events | **Not implemented** — `asset_verifications` has no status/classification field, no count-session concept at all | ❌ |
| 4. Reporting | Cannot finalize without BOTH F&A and OPM sign-off | **Not implemented** — no sign-off field, no finalization concept | ❌ |
| 5. Damage & Disposal | ED approval required before status → Retired; reject path reverts status | Disposal approval sets `status = 'disposed'` (not `'retired'` — that value doesn't exist in the DB); OPM can also approve (not ED-only); reject leaves asset untouched — there's nothing to "revert" since only approval mutates the asset | ⚠️ |
| 6. System Update | Historical records preserved, not overwritten | ⚠️ mostly true, except AssetMovement deletion (§3.4) breaks this for stock-in records | ⚠️ |

---

## 5. Given/When/Then test list

Numbered and prioritized per the spec's own scheme. **P0 = permission bypass/data leakage, P1 =
transaction & lifecycle integrity, P2 = threshold/status boundary logic, P3 = grid/UI/display.**
Implemented as `tests/Feature/RolePermissionMatrixTest.php`,
`tests/Feature/AssetDisposalWorkflowTest.php`, and `tests/Feature/AssetNotificationServiceTest.php`
(see §6).

### P0 — Permission & data leakage
1. **Given** a `staff` user, **when** they `DELETE /api/assets/{id}`, **then** the response is 403 and the asset still exists.
2. **Given** any authenticated user (any role), **when** they `DELETE /api/assets/{id}`, **then** the response is 403 — no role can delete an asset.
3. **Given** a `staff` user, **when** they `POST /api/assets`, **then** the response is 403.
4. **Given** a `staff` user, **when** they `PUT /api/assets/{id}`, **then** the response is 403.
5. **Given** a `finance_manager`, **when** they attempt to manage locations (`POST /api/locations`), **then** the response is 403.
6. **Given** a `staff` user, **when** they `DELETE /api/asset-stocks/{id}`, **then** the response is 403.
7. **Given** an `executive_director`, **when** they attempt `/api/users` management, **then** the response is 403.
8. **Given** any non-OPM/ED role, **when** they call `asset-disposals/{id}/approve`, **then** the response is 403.

### P1 — Transaction & lifecycle integrity
9. **Given** a pending disposal request with `recommended_action = disposal`, **when** OPM approves it, **then** the asset's `status` becomes `disposed` and a `DISPOSAL_REQUEST`-derived approval notification is logged.
10. **Given** a pending disposal request, **when** it is rejected, **then** the asset's `status` is unchanged and the disposal row's `status` becomes `rejected`.
11. **Given** an asset with an existing pending disposal request, **when** a second disposal request is submitted for the same asset, **then** it is rejected (duplicate-pending guard already in `store()` line 33).
12. **Given** a receive-assets submission of quantity 3, **when** it completes, **then** 3 individual `Asset` rows and 3 `AssetMovement` rows sharing one `reference_no` are created (already covered by `ReceiveAssetsTest`).

### P2 — Notification correctness
13. **Given** a staff member flags an asset as `broken`, **when** `flagIssue` runs, **then** an email is sent to every user with role OPM, ED, or Finance Manager (role-based lookup, never a hardcoded address).
14. **Given** a `flagIssue` call with an unrecognized `flaggedBy` user (deleted/invalid), **then** the notification service throws and no email is sent.
15. **Given** a `flagIssue` call with no `note`, **when** the notification is sent, **then** it does not crash and omits the optional field cleanly.
16. **Given** a disposal request is submitted, **when** the notification fires, **then** the Executive Director is a `to` recipient and Finance Manager is `cc`.
17. **Given** the mail transport throws once, **when** `AssetNotificationService::send()` runs, **then** it retries exactly once and logs the failure visibly.

### P3 — Display / reporting
18. **Given** the legacy Blade dashboard/search views reference `maintenance/retired/lost/damaged` status colors, **when** an asset has one of the two real status values (`active`/`disposed`), **then** the color map should not silently fall through to an undefined bucket — flagged as dead code to clean up (not fixed in this change; see Open Decisions).

---

## 6. What was implemented in this change

- **`AssetNotificationService`** (`app/Services/AssetNotificationService.php`) — role-based recipient
  lookup, per-event Mailable rendering, one-retry-then-log-failure behavior, audit log via new
  `notification_logs` table. Wired to `DAMAGE_FLAGGED` (`Api\AssetController::flagIssue`) and
  `DISPOSAL_REQUEST` (`AssetDisposalController::store`, web + API).
- **`MISSING_FIELDS`** — new scheduled command `app:notify-missing-fields`, mirrors
  `SendScheduledAssetReport`'s daily-schedule pattern, flags assets missing price/date/serial for
  >7 days.
- **Permission fixes**: asset create/update/delete now role-gated per §1's matrix (delete restricted
  to OPM — see note below); location management restricted to OPM; `AssetMovement`/`AssetStock`
  destroy restricted to OPM; `asset-verifications.complete` restricted to OPM/Finance Manager as a
  partial stand-in for the spec's dual sign-off.
- **Tests**: `tests/Feature/RolePermissionMatrixTest.php`,
  `tests/Feature/AssetDisposalWorkflowTest.php`, `tests/Feature/AssetNotificationServiceTest.php`.

### What was intentionally NOT implemented (needs a product decision first)

- **`LOW_STOCK`** — no `min_threshold`/`max_threshold`/`current_balance` fields exist, and the
  "Receive Assets" flow already replaced the consumable-stock model with one-asset-per-unit. Building
  this requires deciding whether PEPY still wants a separate consumable-stock concept at all.
- **`COUNT_DISCREPANCY`** and the full reconciliation classification (Confirmed/Relocated/Missing/
  New/Incorrect) — `asset_verifications` has no status field; this is a schema + workflow addition,
  not a wiring task.
- **Dual sign-off (F&A + OPM) before a count report finalizes** — no report/session entity exists to
  attach sign-off to yet.
- **Calendar-anchored Feb/Aug `COUNT_REMINDER`** — current `SendScheduledAssetReport` is interval-based
  from an arbitrary baseline, not fixed to specific months. Left as-is; flagged for follow-up.
- **Stock-Out / append-only transaction correction model** — no stock-out flow exists to enforce
  balance-checking against. `AssetMovement` destroy was restricted to OPM but not converted to
  offset-only, since there's currently only one movement type (`stock_in`).
- **Asset delete "denied for ALL roles"** — the spec's literal rule is that nobody can delete an
  asset, only retire it. This change restricts delete to OPM (a large improvement over "any
  authenticated user") rather than removing it outright, because the Vue asset grid
  (`frontend/src/pages/assets/Index.vue:234`) has a live delete button wired to this route — fully
  removing it needs a frontend change (hide/remove the button) to avoid a dead 403-only control in
  production. Flagged as a follow-up, not silently done as part of a backend-only change.
- **Staff scoped to "own site" view** — not implemented. Neither `users` nor `staff` has a
  `location_id`/site column, so there is currently no data to scope by. Needs a schema decision
  (add `location_id` to `staff` or `users`) before this can be built; faking it against
  `asset_assignments` would only cover assets a staff member is personally assigned, not "their site."

## 7. Open decisions PEPY must confirm before full coverage

1. **Asset ID sequence** — confirmed already global per-category (not per-site) in the current
   implementation (`AssetCodeService`, `asset_code_sequences` table). Keep as-is?
2. **Qty>1 rows** — already resolved in code: auto-expands to individual `Asset` rows with individual
   codes (not bundled). Keep as-is, or is a bundled/quantity-tracked mode still needed for true
   consumables (stationery, cleaning supplies) separate from taggable fixed assets?
3. **Transaction correction model** — currently hard-delete (destroy). Spec wants offset-only. Confirm
   before building a stock-out flow: should corrections be a true void/delete (with audit log) or a
   forced offsetting entry?
4. **Legacy incomplete records** — no import-time flagging exists for truncated/incomplete legacy rows.
   Confirm desired behavior: reject the row, import with a `needs_review` flag, or best-effort parse?
5. **CSV export scope** — confirm whether exports should always return the full register or respect
   active UI filters (currently: need to verify per-export, not yet audited in this pass).
6. **Consumable stock vs. tagged assets** — the biggest open question: does PEPY still need a true
   stock/inventory model (min/max thresholds, stock-in/out, LOW_STOCK alerts) for consumables, given
   that fixed-asset receiving has moved to one-asset-per-unit? If yes, this is new schema + UI, not a
   wiring task on top of `AssetStock`.
7. **Retired vs. Disposed status naming** — spec uses "Retired"; the actual DB/UI uses "disposed".
   Confirm this is just terminology (no functional gap) or whether "Retired" should be a distinct
   status from "Disposed" (e.g. retired-but-kept-for-parts vs. physically disposed of).
