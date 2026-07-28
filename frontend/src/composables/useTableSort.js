import { ref, computed, unref } from 'vue'

function getPath(obj, path) {
  return path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj)
}

// Client-side click-to-sort over a list ref. `paths` optionally maps a sort
// key to a dot-path for nested values (e.g. { category: 'category.name' });
// keys not listed are read directly off the row.
export function useTableSort(itemsRef, { defaultKey = null, defaultDir = 'asc', paths = {} } = {}) {
  const sortKey = ref(defaultKey)
  const sortDir = ref(defaultDir)

  function toggleSort(key) {
    if (sortKey.value === key) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortKey.value = key
      sortDir.value = 'asc'
    }
  }

  const sorted = computed(() => {
    const list = [...(unref(itemsRef) || [])]
    if (!sortKey.value) return list
    const path = paths[sortKey.value] || sortKey.value

    return list.sort((a, b) => {
      let av = getPath(a, path)
      let bv = getPath(b, path)
      av = av === null || av === undefined ? '' : av
      bv = bv === null || bv === undefined ? '' : bv
      if (typeof av === 'string') av = av.toLowerCase()
      if (typeof bv === 'string') bv = bv.toLowerCase()

      if (av < bv) return sortDir.value === 'asc' ? -1 : 1
      if (av > bv) return sortDir.value === 'asc' ? 1 : -1

      return 0
    })
  })

  return { sortKey, sortDir, toggleSort, sorted }
}
