import { ref, computed, unref, watch } from 'vue'
import { useRoute } from 'vue-router'

// Client-side search over a list ref. `fields` is an array of either a key name
// or a function (row) => value. Mirrors the Blade tables' live filter.
//
// The box starts from the page's `?q=` query param, which is how the header's
// global search opens a module with the picked record already filtered. Pass
// `{ fromQuery: false }` for a secondary table that shares a page with one that
// does read it (e.g. the Roles panel under /users).
export function useTableSearch(itemsRef, fields, { fromQuery = true } = {}) {
  const search = ref('')

  const route = fromQuery ? useRoute() : null
  if (route) {
    // Also follow later changes: picking another result while already on this
    // page reuses the component instead of mounting it again.
    watch(() => route.query.q, (q) => {
      if (typeof q === 'string') search.value = q
    }, { immediate: true })
  }

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
