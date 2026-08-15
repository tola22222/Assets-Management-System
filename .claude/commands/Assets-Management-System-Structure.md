# Assets Management System (PEPY) — Structure Reference

Repository: [tola22222/Assets-Management-System](https://github.com/tola22222/Assets-Management-System)
Stack: **Laravel 12 / PHP 8.2** backend + **two frontends** — a legacy Blade UI and a Vue 3 SPA — sharing the same models and business logic.

---

## 1. Roles

Roles are stored as a plain string on `users.role` (four fixed values, matching the Asset Checking & Counting Manual).

| Role | Code | Summary |
|---|---|---|
| Operations/HR Manager | `operations_hr_manager` | Primary admin — full asset CRUD, approves most workflows, manages suppliers, submits disposal requests, only role with sidebar access to Users/Settings/Activity Logs |
| Finance Manager | `finance_manager` | "Own-scope" edit rights shared with OPM (assets, suppliers, assignments); receives financial notifications |
| Executive Director | `executive_director` | Sole approver of asset **disposals** (independent review of OPM's submissions — cannot approve its own request) |
| Staff | `staff` | Site-scoped via `staff.location_id`; sees/verifies only their own site's assets. If no location is set, access fails **open** (sees everything) by deliberate design |

Enforcement is server-side (route `role:` middleware + in-controller checks). The frontend only hides buttons/nav items per role for UX — it is not a security boundary.

---

## 2. Sidebar Navigation (Vue SPA — `frontend/src/layouts/AppLayout.vue`)

Only `operations_hr_manager` is treated as "admin" (`isAdmin`) for sidebar purposes. Finance Manager, Executive Director, and Staff all see the non-admin variant.

### Overview *(all roles)*
- Dashboard — `/`

### Second group — content differs by role
**Admin (OPM) — "Asset Management":**
- Asset Register — `/assets`
- Stock — `/stock`
- Assignments — `/asset-assignments`
- Transfers — `/asset-transfers`
- Verification — `/asset-verifications`
- Disposals — `/asset-disposals`

**Non-admin (Finance / ED / Staff) — "My Assets":**
- Assignments — `/asset-assignments`
- Transfer Requests — `/asset-transfers`
- Verification — `/asset-verifications`

*(Stock and Disposals have no sidebar link for non-admin roles, though route-level access may still permit direct navigation depending on backend role checks — see Section 4.)*

### People & Programs *(all roles)*
- Staff Directory — `/staff`
- Programs — `/programs`

### System Setup *(all roles)*
- Categories — `/categories`
- Locations — `/locations`
- Suppliers — `/suppliers`

### Insight *(all roles)*
- Reports — `/reports`

### Pinned bottom — content differs by role
**Admin (OPM) — "Setting" group:**
- User Management — `/users`
- System Settings — `/settings`
- Activity Logs — `/activity-logs`

**Non-admin (Finance / ED / Staff):**
- Profile — `/profile`

**All roles:** Logout

*Not in the sidebar but reachable in-app: Notification bell, theme (dark/light) toggle, language switcher — all live in the top header, not the sidebar.*

---

## 3. Full Route List (Vue SPA — `frontend/src/router/index.js`)

| Path | Name | Auth | Admin-only |
|---|---|---|---|
| `/login` | login | guest only | — |
| `/` | dashboard | ✔ | |
| `/assets` | assets | ✔ | |
| `/assets/import` | assets-import | ✔ | ✔ |
| `/categories` | categories | ✔ | |
| `/locations` | locations | ✔ | |
| `/asset-assignments` | asset-assignments | ✔ | |
| `/asset-transfers` | asset-transfers | ✔ | |
| `/asset-verifications` | asset-verifications | ✔ | |
| `/asset-disposals` | asset-disposals | ✔ | |
| `/stock` | stock | ✔ | |
| `/programs` | programs | ✔ | |
| `/staff` | staff | ✔ | |
| `/suppliers` | suppliers | ✔ | |
| `/users` | users | ✔ | ✔ |
| `/settings` | settings | ✔ | ✔ |
| `/activity-logs` | activity-logs | ✔ | ✔ |
| `/reports` | reports | ✔ | |
| `/qr-scan` | qr-scan | ✔ | |
| `/search` | search | ✔ | |
| `/notifications` | notifications | ✔ | |
| `/profile` | profile | ✔ | |

`adminOnly` routes redirect to Dashboard if `user.role !== 'operations_hr_manager'`. Note `/assets/import`, `/stock`, and reachable pages exist even where the sidebar hides them (e.g. Stock has no admin-only route flag, so any authenticated role can navigate there directly — access to specific actions inside is still gated by the backend).

---

## 4. Core Feature Modules

- **Asset Registry** — CRUD, auto-generated codes (`PEY-[SITE]-[CATEGORY]-[####]`), per-asset QR code (PNG, links to a public condition-report page), bulk import from Excel/CSV (auto-detects PEPY's real workbook layout vs. a simple template; upserts by code)
- **Workflow entities** (approve/reject/complete state machines): Asset **Assignment**, **Transfer**, **Return**, **Verification** (counts), **Disposal** (ED-only approval)
- **Stock / Consumables** — separate module, API/SPA-only, no Blade equivalent. Receive/issue/delete; low-stock notification trigger; own `PEY-STK-####` code sequence. Everyone can view; only OPM/Finance can receive, issue, or delete
- **Dashboard** — role-specific payload (different JSON/view for staff vs. admin); "assets registered over time" chart
- **Global Search** — cross-entity (assets, staff, users, categories, suppliers, locations, programs); SPA only
- **QR Scanning** — public, unauthenticated asset lookup + condition-update endpoint
- **Notifications** — in-app bell (`Notification` model) + separate delivery audit log (`NotificationLog`); central email dispatcher covers 6 event types: `DAMAGE_FLAGGED`, `DISPOSAL_REQUEST`, `MISSING_FIELDS`, `COUNT_REMINDER`, `COUNT_DISCREPANCY`, `LOW_STOCK`
- **Scheduled Jobs** — weekly/daily digests: missing fields, count reminders (fixed Feb 1 / Aug 1 dates), count discrepancies, periodic register report to Finance/ED/OPM
- **Reference Data** — Categories, Locations (13 PEPY sites), Programs, Suppliers, Staff
- **User Management** *(admin only)* — CRUD, account locking (kills active sessions/tokens), self-service profile/password
- **Settings** *(admin only)* — theme color, language (English/Khmer via i18n), backup/restore (sqlite-only), report interval, staff report opt-in
- **Activity Log** *(admin only)* — audit trail of actions
- **Reports** — asset register reporting

### Removed / legacy (do not resurrect)
- Separate "Receive Assets" stock-in workflow (`asset-stocks`)
- "Stock Movements" audit log (`AssetMovement` model/table)
- `StockTransferController`, `InventoryController`, `AssetReportController` (pre-dated current workflow, never wired to routes)
- `Role` model (roles are a plain `users.role` string, not a relation)

---

## 5. Legacy Blade UI

A parallel server-rendered UI (`backend/resources/views/`, `backend/routes/web.php`, root-namespace controllers) exists for most — but not all — of the same domain features. Notable difference: **Stock/Consumables has no Blade equivalent** (API/SPA-only). The legacy Blade dashboard lives at `/blade` (the Vue SPA now owns `/`).
