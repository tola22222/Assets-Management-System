import { ref, reactive, computed } from 'vue'
import http from '../api/http'

// Server-driven table state: page/perPage/sort/search/filters are sent as
// query params to `endpoint`, which must return a Laravel paginate() envelope
// ({ data, current_page, per_page, total, last_page }).
export function useServerTable(endpoint, { defaultSort = 'created_at', defaultDir = 'desc', filterKeys = [] } = {}) {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)

  const page = ref(1)
  const perPage = ref(25)
  const total = ref(0)
  const lastPage = ref(1)

  const search = ref('')
  const sortBy = ref(defaultSort)
  const sortDir = ref(defaultDir)
  const filters = reactive(Object.fromEntries(filterKeys.map((k) => [k, ''])))

  let debounceTimer = null
  let seq = 0

  async function fetchPage() {
    loading.value = true
    error.value = null
    const mySeq = ++seq
    try {
      const params = {
        page: page.value,
        per_page: perPage.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
      }
      if (search.value) params.search = search.value
      filterKeys.forEach((k) => {
        if (filters[k]) params[k] = filters[k]
      })
      const { data } = await http.get(endpoint, { params })
      if (mySeq !== seq) return
      items.value = data.data
      total.value = data.total
      lastPage.value = data.last_page
      page.value = data.current_page
    } catch (e) {
      if (mySeq !== seq) return
      error.value = e
    } finally {
      if (mySeq === seq) loading.value = false
    }
  }

  function goToPage(p) {
    page.value = Math.min(Math.max(1, p), lastPage.value || 1)
    fetchPage()
  }

  function setPerPage(n) {
    perPage.value = n
    page.value = 1
    fetchPage()
  }

  function toggleSort(key) {
    if (sortBy.value === key) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortBy.value = key
      sortDir.value = 'asc'
    }
    page.value = 1
    fetchPage()
  }

  function setSearch(v) {
    search.value = v
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      page.value = 1
      fetchPage()
    }, 350)
  }

  function applyFilters() {
    page.value = 1
    fetchPage()
  }

  function clearFilters() {
    filterKeys.forEach((k) => {
      filters[k] = ''
    })
    page.value = 1
    fetchPage()
  }

  const hasActiveFilters = computed(() => filterKeys.some((k) => !!filters[k]))

  // AppDataTable (v-data-table-server) wants sort-by as [{key, order}].
  const sortByVuetify = computed(() => (sortBy.value ? [{ key: sortBy.value, order: sortDir.value }] : []))

  // Single handler for AppDataTable's @update:options — sets page/perPage/sort
  // from Vuetify's options object and fetches once, instead of the 2-3 separate
  // fetches that calling goToPage/setPerPage/toggleSort individually would cause.
  function handleOptions({ page: p, itemsPerPage: ipp, sortBy: sb }) {
    page.value = p
    perPage.value = ipp
    if (sb && sb.length) {
      sortBy.value = sb[0].key
      sortDir.value = sb[0].order
    } else {
      sortBy.value = defaultSort
      sortDir.value = defaultDir
    }
    fetchPage()
  }

  return {
    items, loading, error,
    page, perPage, total, lastPage,
    search, setSearch,
    sortBy, sortDir, sortByVuetify, toggleSort, handleOptions,
    filters, hasActiveFilters, applyFilters, clearFilters,
    goToPage, setPerPage, fetchPage,
  }
}
