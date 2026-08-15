# Asset Management System — PEPY Empowering Youth

**Project kickoff · Capstone design meeting**
Prepared by Manin Oem, Operations & HR Manager · PEPY Empowering Youth, Siem Reap
manin@pepyempoweringyouth.org | Tel. 012 782 785

> A digital home for PEPY Empowering Youth's fixed-asset register — replacing the annual Excel count with a live, shared system.

---

## What we'll cover today

1. **Where we are today** — How PEPY currently tracks assets, and where the spreadsheet is straining.
2. **The data you'll be working with** — 927 real assets, 4 categories, 13 sites — the shape of the problem.
3. **What the new system should do** — Goals, must-have features, and who will use it day to day.
4. **A first design direction** — Mockups for a dashboard, register, intake form, and reports — yours to challenge.
5. **Roadmap & what we need from you** — Suggested phases and the questions we'd like your team to take away.

---

## PEPY Empowering Youth

Education & youth-development NGO based in Siem Reap, working across one main office and 12 partner high schools.

Example tag: `PEY-SR-MOV-0085`
*Every item PEPY owns — from a Hilux truck to a plastic chair — already carries a tag like this.*

### How assets are tracked today
- One master Excel workbook, re-counted by hand once a year (this year: 31 Aug 2026).
- 12 separate tabs per category/site, plus rolled-up summary sheets that must be kept in sync manually.
- Asset IDs (e.g. `PEY-SR-FAF-0592`) are typed in by whoever does the count — sequence, not software, keeps them unique.
- Purchase price, date, and serial number are filled in only when someone remembers to.

---

## The current register, by the numbers

*Pulled from the Aug-2026 count — this is the real dataset the team will design against.*

| Metric | Value |
|---|---|
| Assets on the register | **927** |
| Categories (vehicles, furniture, computers, equipment) | **4** |
| Sites (PEPY office + 12 partner high schools) | **13** |
| Items with a recorded purchase price | **78%** |

### Category breakdown
| Category | Count |
|---|---|
| Furniture & Fixture | 521 |
| Computer Equipment | 257 |
| Equipment Units | 139 |
| Motor & Vehicle | 10 |

> **Why this matters to the build:** 249 assets are missing a price, and even more are missing a serial number or purchase date. A spreadsheet can't require a field before saving a row — a system can.

---

## Where the spreadsheet is straining

| Issue | Detail |
|---|---|
| **No shared source of truth** | The office copy and the 12 site tabs can drift out of sync between annual counts. |
| **IDs assigned by hand** | Asset tags like `PEY-SR-FAF-0592` are typed manually — easy to duplicate or skip a number. |
| **Nothing is required** | Price, serial number, and purchase date are only ever filled in when someone remembers. |
| **Hard to search or filter** | Finding "every laptop at Sen Sok HS" means scrolling 927 rows across several sheets. |
| **No condition history** | "Broken leg" notes sit in a remark cell with no record of when it broke or who flagged it. |
| **Annual, not real-time** | The register is only ever as current as the last physical count — a year can pass in between. |

---

## What we want the new system to do

- ✅ **One shared register** — Every site sees the same live data — no more re-merging tabs once a year.
- ✅ **Assign tags automatically** — New assets get a valid `PEY-[SITE]-[CATEGORY]-[####]` ID the moment they're entered.
- ✅ **Make key fields required** — Price, location, and category can't be skipped when an asset is added.
- ✅ **Search and filter instantly** — Find every asset at a site, in a category, or below a value in seconds.
- ✅ **Track condition over time** — Flag damage, repairs, and retirements with a date and a note, not a stray remark.
- ✅ **Report on demand** — Generate the annual count summary and value totals without manual recalculation.

---

## A data model grounded in the real register

| Field | Format | Rule |
|---|---|---|
| Description | Text | Required |
| Category | MOV / FAF / COM / EQU | Required |
| Location | Office or one of 12 schools | Required |
| Asset ID | Auto-generated on save | System-set |
| Purchase date | Date | Recommended |
| Price (USD) | Number | Recommended |
| Serial number | Text | Optional |
| Assigned to / dept. | Text | Optional |
| Status | In use / Needs repair / Retired | Required |
| Remark | Free text | Optional |

### Asset tag scheme

`PEY-SR-FAF-0928`

| Segment | Meaning |
|---|---|
| `PEY` | Fixed organization prefix |
| `SR` | Site code (SR = office, KL = Kralanh HS, …) |
| `FAF` | Category code (MOV / FAF / COM / EQU) |
| `0928` | Sequence — system-issued, never reused |

> This is the one change that matters most: today, IDs are typed by hand during the annual count. In the new system, the ID is a side-effect of filling out the form correctly.

---

## Design direction — Dashboard

*First-pass mockup — teal & sand palette echoes PEPY's existing SEST presentation branding.*

**"Good morning, Manin"** — snapshot of PEPY's fixed assets across the office and all learning centers · last counted 31 Aug 2026

| Metric | Value |
|---|---|
| Total assets on register | 927 |
| Asset categories | 4 |
| Offices & learning centers | 13 |
| Recorded value (78% priced) | $182K · 249 items missing price |

**Assets by category** (furniture & fixtures make up the bulk):
- Furniture & Fixture — 521
- Computer Equipment — 257
- Equipment Units — 139
- Motor & Vehicle — 10

**Needs attention** (records missing required fields):
- Honda CRV `PEY-SR-MOV-0086` — no purchase price
- Fan – Ceiling `PEY-KL-FAF-0290` — no purchase date
- Plastic Chair `PEY-KL-FAF-0078` — flagged: broken leg
- Table-Arm Chair `PEY-SR-FAF-0657` — no serial no.
- Laptop (x17) Sen Sok HS — annual check overdue

Sidebar nav: Overview (Dashboard) · Manage (Asset Register, Add Asset, Locations) · Insight (Reports)

---

## Design direction — Asset Register

*Search across all 927 items, or filter to one category in a tap. Asset ID rendered as a physical-tag chip — a nod to the tags already on every item.*

Search bar + category filter chips: **All / Furniture / Computer / Equipment / Vehicle**

| Asset ID | Description | Category | Location | Assigned To | Status | Value |
|---|---|---|---|---|---|---|
| PEY-SR-MOV-0085 | Truck Toyota Hilux Revo — Purchased Feb 2022 | Vehicle | PEPY Office | Office pool | In use | $28,500 |
| PEY-SR-COM-0212 | Laptop - Dell Latitude — Assigned to Program team | Computer | PEPY Office | Soury | In use | $540 |
| PEY-KL-FAF-0078 | Plastic Chair — Broken leg, flagged in count | Furniture | Sen Sok HS | Dream Program | Needs repair | $8 |
| PEY-SR-EQU-0041 | Security Camera — ColorVu Fixed Bullet Network | Equipment | PEPY Office | Operations | In use | $95 |
| PEY-VR-FAF-0472 | Fan - On wall — Installed Feb 2022 | Furniture | Varin HS | Dream Program | In use | $60 |
| PEY-SR-COM-0059 | LCD Projector — Shared classroom equipment | Computer | Kralanh HS | Dream Program | In use | $310 |
| PEY-SR-MOV-0086 | Honda CR-V — Donation from Child's Dream | Vehicle | PEPY Office | Office pool | In use | — |
| PEY-SR-EQU-0018 | Smart TV 55" Samsung — Conference room | Equipment | PEPY Office | Operations | In use | $610 |
| PEY-BS-FAF-0528 | Fan - Standing — Dream classroom | Furniture | Banteay Srei HS | Dream Program | In use | $48 |
| PEY-SR-COM-0004 | Desktop Computer — No warranty on file | Computer | PEPY Office | Finance Team | Retiring | $220 |

---

## Design direction — Add a new asset

*"Add a new asset" screen, updated with photo upload.*

Fill in the details below — a photo helps confirm condition at the next count, and the tag is generated automatically.

**Form fields:** Description · Category · Location · Purchase date · Price (USD) · Serial number · Assigned to/department · Status · Remark · Photo upload (PNG/JPG up to 5MB)

**Auto-generated tag panel:**
`PEY-SR-FAF-0929`
PEY – LOCATION – CATEGORY – SEQUENCE
*The tag updates as soon as category and location are chosen, so numbers never collide with what's already on the register.*

**Live preview example:**
Standing fan · Furniture · In use · `PEY-SR-FAF-0929` · PEPY Office

---

## Design direction — Reports

*Roll-ups for the annual count, without a manual recalculation. Data-completeness view turns "249 items missing a price" into an actionable checklist.*

**Assets by location** (top 8 sites by item count):
| Site code | Count |
|---|---|
| OF/LC | 612 |
| KL | 54 |
| SS | 48 |
| VR | 27 |
| BS | 21 |
| SK | 19 |

**Data completeness** (fields required for a clean annual count):
| Field | % Complete |
|---|---|
| Has Asset ID | 100% |
| Has purchase price | 73% |
| Has purchase date | 58% |
| Has serial no. | 21% |

> **Why this matters:** Required fields at entry (not after the fact) are the single biggest fix the new system offers over the spreadsheet — no more "counted in August" cleanup sprints.

### Annual count reconciliation flow

```
Begin annual count
  → HR exports register (current asset list, all sites)
  → Accounting counts on-site (physically checks each location)
  → Record physical count (logs actual quantities found)
  → Counts match register?
        No  → Resolve discrepancy (see detail flow) → Register updated
        Yes → Mark items confirmed (no further action needed)
  → Compile reconciliation report (confirmed, relocated, lost, new)
  → HR reviews and signs off (confirms figures for the books)
  → Register updated & archived
```

**On-site checklist example (Accountant):**
- Chair → Confirmed
- Laptop → Confirmed
- Fan → Not found

---

## Suggested build phases

*A rough shape for a capstone timeline — open to the team's scoping.*

| Phase | Focus | Scope |
|---|---|---|
| **Phase 1** | Core register | Add / edit / search assets, auto-generated tags, the 4 categories and 13 locations, required fields. |
| **Phase 2** | Reporting & roles | Dashboard, category & location reports, view-only access for site staff vs. full access for Operations. |
| **Phase 3** | Depth & polish | Condition history, photo attachments, CSV export for ED, depreciation view. |

---

## Who will use this day to day

*Three access levels cover how PEPY actually operates across sites.*

| Role | Access | Responsibilities |
|---|---|---|
| **Operations & HR (HR)** | Full access | Add, edit, retire assets; run reports; manage the annual count. Manin's team today. |
| **Finance staff (F&A)** | Site + view access | See and update assets tied to their program or department, across all sites. |
| **Staff (S)** | View + flag only | Look up what's on-site at their school, and flag damage or loss for Operations to action. |

---

## Next steps & questions for the team

### What PEPY will provide
- The full 2025–2026 asset workbook (anonymized categories shown here)
- A walkthrough of the annual counting process on-site
- Feedback rounds on wireframes before build begins
- Access to Manin as the primary point of contact

### Questions to bring to your next meeting
- Web app, mobile app, or both — how will school focal points access it?
- Should it work offline at sites with unreliable internet?
- What's realistic for authentication — shared logins vs. individual accounts?
- Where should the data live, and who owns backups?

---

## Thank you

Looking forward to building this with you.

**Manin Oem** · Operations & HR Manager
PEPY Empowering Youth, Siem Reap
