import { reactive, computed, unref } from 'vue'

// Client-side exact-match dropdown filters over a list ref. `matchers` maps
// a filter key to (row, selectedValue) => boolean. Every filter starts
// unset ('') and is skipped until the user picks a value.
export function useTableFilter(itemsRef, matchers) {
  const filters = reactive(Object.fromEntries(Object.keys(matchers).map((k) => [k, ''])))

  const filtered = computed(() => {
    let list = unref(itemsRef) || []
    for (const key of Object.keys(matchers)) {
      const value = filters[key]
      if (value === '' || value === null || value === undefined) continue
      list = list.filter((row) => matchers[key](row, value))
    }

    return list
  })

  const hasActiveFilters = computed(() => Object.values(filters).some((v) => v !== ''))

  function clearFilters() {
    Object.keys(filters).forEach((k) => { filters[k] = '' })
  }

  return { filters, filtered, hasActiveFilters, clearFilters }
}
