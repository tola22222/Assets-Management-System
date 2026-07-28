import { ref, computed, unref } from 'vue'

// Client-side search over a list ref. `fields` is an array of either a key name
// or a function (row) => value. Mirrors the Blade tables' live filter.
export function useTableSearch(itemsRef, fields) {
  const search = ref('')

  const filtered = computed(() => {
    const list = unref(itemsRef) || []
    const q = search.value.trim().toLowerCase()
    if (!q) return list
    return list.filter((row) =>
      fields
        .map((f) => (typeof f === 'function' ? f(row) : row?.[f]))
        .filter((v) => v !== null && v !== undefined)
        .join(' ')
        .toLowerCase()
        .includes(q)
    )
  })

  return { search, filtered }
}
