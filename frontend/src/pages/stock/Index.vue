<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import LocationFilter from '../../components/ui/LocationFilter.vue'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useTableFilter } from '../../composables/useTableFilter'
import { useAuthStore } from '../../stores/auth'
import SearchSelect from '../../components/ui/SearchSelect.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'
import { downloadExcel, exportNote } from '../../utils/excelExport'

// Read-only register: stock rows are recorded through the Asset create/import
// flow, so this page only views them — no issue or delete actions.
const { t } = useI18n()
const auth = useAuthStore()
// HR and the Accountant also see every location's holdings by category.
const isAdmin = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))

// ---- Assets by category (HR / Accountant) ---------------------------------
// What each location holds, by category and model: PEPY Office → COM
// Computer → Dell 10, Smart phone 20. Counted from the register itself, the
// same units the location card counts (on the register, not lost / broken).
// Every unit still on the register (not disposed), whatever its condition.
const allAssets = ref([])
// Condition filter: blank keeps the usual view (usable units — good or fair);
// picking one shows only the units in that condition, e.g. broken or lost.
const conditionFilter = ref("")
const conditionOptions = computed(() => ["good", "fair", "broken", "lost"]
  .map((c) => ({ value: c, label: t(`assets.condition_${c}`) })))
const registerAssets = computed(() => allAssets.value.filter((a) => (conditionFilter.value
  ? a.condition === conditionFilter.value
  : !["lost", "broken"].includes(a.condition))))
const categoryLoading = ref(false)

async function loadRegister() {
  categoryLoading.value = true
  try {
    const { data } = await http.get('/assets')
    allAssets.value = data.filter((a) => a.status !== 'disposed')
  } catch {
    allAssets.value = []
  } finally {
    categoryLoading.value = false
  }
}

// One row per model at each location: Location · Category · Model · Qty.
const categoryRows = computed(() => {
  const rows = new Map()
  for (const a of registerAssets.value) {
    const key = `${a.location_id ?? 'none'}|${a.category_id ?? 'none'}|${a.name}`
    if (!rows.has(key)) {
      rows.set(key, {
        key,
        location_id: a.location_id,
        location: a.location?.name || '',
        location_code: a.location?.code || '',
        category_id: a.category_id,
        category: a.category?.name || '',
        category_code: a.category?.short_name || '',
        model: a.name,
        // The model's photo, from its units: every distinct one, so a unit
        // whose file is gone from storage falls back to the next (see rowImage).
        images: [],
        qty: 0,
      })
    }
    const row = rows.get(key)
    row.qty++
    if (a.image_url && !row.images.includes(a.image_url)) row.images.push(a.image_url)
  }
  return [...rows.values()]
})

// Photos that failed to load; a row shows its first one that hasn't, or the
// placeholder once none is left.
const brokenImages = ref(new Set())
const rowImage = (r) => r.images.find((url) => !brokenImages.value.has(url)) || null
function imageFailed(url) {
  brokenImages.value = new Set(brokenImages.value).add(url)
}

// Filters: its own location drop-down, category, asset name, and search.
const catLocation = ref('')
const categoryFilter = ref('')
const categoryOptions = computed(() => {
  const seen = new Map()
  registerAssets.value.forEach((a) => a.category && seen.set(String(a.category.id), a.category))
  return [...seen.values()].sort((x, y) => x.name.localeCompare(y.name))
    .map((c) => ({ value: String(c.id), label: c.name, sub: c.short_name || undefined }))
})
// Asset name filter: every model name on the register, picked by name.
const assetNameFilter = ref('')
const assetNameOptions = computed(() => [...new Set(registerAssets.value.map((a) => a.name))]
  .sort((x, y) => x.localeCompare(y)).map((name) => ({ value: name, label: name })))
const { search: categorySearch, filtered: categorySearched } = useTableSearch(categoryRows, ['location', 'location_code', 'category', 'category_code', 'model'])
const categoryFiltered = computed(() => categorySearched.value.filter((r) =>
  (!catLocation.value || String(r.location_id) === String(catLocation.value))
  && (!categoryFilter.value || String(r.category_id) === categoryFilter.value)
  && (!assetNameFilter.value || r.model === assetNameFilter.value)))
const { sortKey: catSortKey, sortDir: catSortDir, toggleSort: catToggleSort, sorted: categorySorted } = useTableSort(categoryFiltered, { defaultKey: 'qty', defaultDir: 'desc' })
const categoryTotal = computed(() => categoryFiltered.value.reduce((n, r) => n + r.qty, 0))
const { page: catPage, rowsPerPage: catRows, total: catTotal, paged: categoryPaged } = usePagination(categorySorted)

const items = ref([])
const locationStats = ref([])
const loading = ref(true)

async function fetchAll() {
  loading.value = true
  const { data } = await http.get('/stock-items')
  items.value = data
  loading.value = false
}
async function loadLocationStats() {
  const { data } = await http.get('/stock-items/by-location')
  locationStats.value = data
}

// ---- Total Assets by Location ------------------------------------------
// A live tally of the Asset Register per site (already sorted busiest-first
// server-side), not a stored balance — so it always matches the register.
// The filter bar narrows this card: search by site name/code, or pick one
// site.
const { search, filtered: searchedSites } = useTableSearch(locationStats, ['name', 'code'])
const { filters, filtered: visibleSites } = useTableFilter(searchedSites, {
  location: (row, val) => String(row.location_id) === String(val),
})
// Busiest site first (the order the grid shows; the export uses it too).
const { sorted: sitesSorted } = useTableSort(visibleSites, { defaultKey: 'total', defaultDir: 'desc' })

// ---- Consumables (CSV export only) ----------------------------------------
// The consumables table was removed from this page; the header's Export CSV
// still downloads the list, LOW first. Status is computed server-side per
// item, but sorting it "LOW first" needs a numeric rank — alphabetical
// ('high' < 'low' < 'normal') would put High first.
const STATUS_RANK = { low: 0, normal: 1, high: 2 }
const rankedItems = computed(() => items.value.map((i) => ({ ...i, status_rank: STATUS_RANK[i.status] ?? 1 })))

const { sorted: visible } = useTableSort(rankedItems, {
  defaultKey: 'status_rank', defaultDir: 'asc',
  paths: { location: 'location.name', balance: 'balance', threshold: 'min_threshold', updated: 'updated_at' },
})

// ---- CSV export -----------------------------------------------------------
// ---- Excel export -----------------------------------------------------------
// Both tables on this page, as filtered on screen: totals by location, and —
// for HR / the Accountant — every model per location, grouped by location.
async function exportCsv() {
  const sheets = [{
    name: t('stock.title'),
    columns: [
      { key: 'code', header: t('locations.code'), type: 'code', width: 12 },
      { key: 'name', header: t('common.location'), type: 'text' },
      { key: 'total', header: t('stock.total_assets'), type: 'qty', width: 14 },
    ],
    rows: sitesSorted.value.map((l) => ({ code: l.code || '', name: l.name || t('stock.no_location'), total: l.total })),
    totalLabel: t('export.total'),
  }]
  if (isAdmin.value) {
    sheets.push({
      name: t('stock.by_category'),
      columns: [
        { key: 'category_code', header: t('assets.code'), type: 'code', width: 10 },
        { key: 'category', header: t('stock.category'), type: 'text' },
        { key: 'model', header: t('common.asset'), type: 'text' },
        { key: 'qty', header: t('common.quantity'), type: 'qty', width: 10 },
      ],
      rows: [...categorySorted.value].sort((a, b) => (a.location || '').localeCompare(b.location || '')),
      group: {
        by: (r) => r.location || t('stock.no_location'),
        label: (key, rows) => (rows[0].location_code ? `${key} ( ${rows[0].location_code} )` : key),
        code: (key, rows) => rows[0].location_code || key,
        codeColumn: 'category_code',
      },
      totalLabel: t('export.total'),
    })
  }
  await downloadExcel({
    fileName: 'asset-allocation',
    title: t('stock.title'),
    subtitle: t('export.inventory_scope'),
    note: exportNote(t('export.generated')),
    sheets,
  })
}

onMounted(() => {
  fetchAll()
  loadLocationStats()
  if (isAdmin.value) loadRegister()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">

      <!-- Table 1 — total assets by location: a live tally of the register per
           site, so a site sitting at zero is shown rather than hidden. Same
           layout as every list page: header, filter bar, table, pagination. -->
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('stock.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('stock.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button @click="exportCsv" class="btn-ghost btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0l4.5-4.5M12 16.5V3" /></svg>
              {{ t('stock.export_csv') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('stock.search_sites_placeholder')" />
          </div>
          <LocationFilter v-model="filters.location" />
        </div>

        <!-- The old grid: one row per site — code, name, total — busiest first.
             Three columns only on very wide screens, so names aren't cut. -->
        <div v-if="visibleSites.length" class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-x-6 gap-y-1">
          <div
            v-for="l in sitesSorted"
            :key="l.location_id ?? 'unplaced'"
            class="flex items-center justify-between gap-3 px-2.5 py-2 rounded-lg border-b border-line/60"
          >
            <span class="flex items-center gap-2 min-w-0">
              <span v-if="l.code" class="id-chip flex-shrink-0">{{ l.code }}</span>
              <span class="text-sm truncate" :class="l.location_id ? 'font-semibold text-fg' : 'font-semibold text-amber-600 dark:text-amber-400'">
                {{ l.name || t('stock.no_location') }}
              </span>
            </span>
            <span class="flex items-center gap-2 flex-shrink-0">
              <span class="text-sm font-bold w-8 text-right" :class="l.total ? 'text-fg' : 'text-faint'">{{ l.total }}</span>
            </span>
          </div>
        </div>
        <p v-else class="text-sm text-faint">{{ locationStats.length ? t('stock.no_sites_match') : t('stock.no_locations') }}</p>
      </div>

      <!-- Assets by category (HR / Accountant): one table, a row per model at
           each location, filtered by location, category and search. -->
      <div v-if="isAdmin" class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h2 class="font-display text-xl font-bold text-fg tracking-tight">{{ t('stock.by_category') }}</h2>
            <p class="text-muted text-sm mt-1">{{ t('stock.by_category_hint') }}</p>
          </div>
          <span class="badge-neutral gap-1 flex-shrink-0">
            <span class="font-bold text-fg">{{ categoryTotal }}</span>
            <span class="lowercase">{{ t('stock.total_assets') }}</span>
          </span>
        </div>

        <!-- One row on a desktop: the search and the four filters share the width. -->
        <div class="flex flex-wrap lg:flex-nowrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px] lg:min-w-0">
            <SearchInput v-model="categorySearch" :placeholder="t('common.search')" />
          </div>
          <LocationFilter v-model="catLocation" class="lg:min-w-0 lg:flex-1" />
          <SearchSelect v-model="categoryFilter" class="min-w-[13rem] lg:min-w-0 lg:flex-1" input-class="filter-select"
            :empty-label="t('assets.all_categories')" :aria-label="t('assets.all_categories')" :options="categoryOptions" />
          <SearchSelect v-model="assetNameFilter" class="min-w-[13rem] lg:min-w-0 lg:flex-1" input-class="filter-select"
            :empty-label="t('stock.all_asset_names')" :aria-label="t('stock.all_asset_names')" :options="assetNameOptions" />
          <SearchSelect v-model="conditionFilter" class="min-w-[13rem] lg:min-w-0 lg:flex-1" input-class="filter-select"
            :empty-label="t('stock.condition_usable')" :aria-label="t('assets.condition')" :options="conditionOptions" />
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="th-sort" @click="catToggleSort('location')">{{ t('common.location') }}<TableSortIcon :active="catSortKey === 'location'" :direction="catSortDir" /></th>
                <th class="th-sort" @click="catToggleSort('category')">{{ t('stock.category') }}<TableSortIcon :active="catSortKey === 'category'" :direction="catSortDir" /></th>
                <th class="th-sort" @click="catToggleSort('model')">{{ t('common.asset') }}<TableSortIcon :active="catSortKey === 'model'" :direction="catSortDir" /></th>
                <th class="th-sort text-right" @click="catToggleSort('qty')">{{ t('common.quantity') }}<TableSortIcon :active="catSortKey === 'qty'" :direction="catSortDir" /></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in categoryPaged" :key="r.key">
                <td>
                  <span class="flex items-center gap-2 min-w-0">
                    <span v-if="r.location_code" class="id-chip flex-shrink-0">{{ r.location_code }}</span>
                    <span class="font-medium truncate" :class="r.location ? 'text-fg' : 'text-amber-600 dark:text-amber-400'">{{ r.location || t('stock.no_location') }}</span>
                  </span>
                </td>
                <td>
                  <span class="flex items-center gap-2 min-w-0">
                    <span v-if="r.category_code" class="id-chip flex-shrink-0">{{ r.category_code }}</span>
                    <span class="truncate">{{ r.category || t('common.n_a') }}</span>
                  </span>
                </td>
                <td>
                  <span class="flex items-center gap-3 min-w-0">
                    <img v-if="rowImage(r)" :key="rowImage(r)" :src="rowImage(r)" :alt="r.model" loading="lazy" @error="imageFailed(rowImage(r))" class="w-10 h-10 rounded-lg object-cover border border-line flex-shrink-0" />
                    <span v-else class="w-10 h-10 rounded-lg bg-surface-2 border border-line flex items-center justify-center text-faint flex-shrink-0">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><polyline points="21 15 16 10 5 21" /></svg>
                    </span>
                    <span class="font-medium text-fg">{{ r.model }}</span>
                  </span>
                </td>
                <td class="text-right font-bold text-fg">{{ r.qty }}</td>
              </tr>
              <tr v-if="!categoryLoading && !categorySorted.length">
                <td colspan="4" class="py-10 text-center text-faint">{{ t('stock.no_category_holdings') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="catPage" v-model:rows-per-page="catRows" :count="catTotal" />
      </div>
    </div>

  </AppLayout>
</template>
