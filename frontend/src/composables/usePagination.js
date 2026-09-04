import { ref, computed, watch } from 'vue'

/**
 * Client-side slice for a data table, sitting on top of whatever the page's
 * search/filter/sort composables already produced.
 *
 * Pass the FINAL list (the sorted/filtered one) — pagination is the last step,
 * so searching still looks at every row, not just the page on screen.
 *
 * `page` is zero-based to match Material UI's TablePagination.
 */
export function usePagination(source, initialRowsPerPage = 10) {
  const page = ref(0)
  const rowsPerPage = ref(initialRowsPerPage)

  const total = computed(() => source.value?.length ?? 0)

  const paged = computed(() => {
    const start = page.value * rowsPerPage.value
    return (source.value ?? []).slice(start, start + rowsPerPage.value)
  })

  // Filtering down while parked on a high page would otherwise leave an empty
  // table with no obvious way back, so clamp to the last page that still exists.
  watch([total, rowsPerPage], () => {
    const lastPage = Math.max(0, Math.ceil(total.value / rowsPerPage.value) - 1)
    if (page.value > lastPage) page.value = lastPage
  })

  return { page, rowsPerPage, total, paged }
}
