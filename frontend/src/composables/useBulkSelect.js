import { ref, computed, unref } from 'vue'

// "Select all" checkbox state over a list ref (typically the same
// search/sort/filter-derived list a table already renders), so selecting
// "all" only ever covers what's currently visible, not the whole dataset.
export function useBulkSelect(itemsRef) {
  const selectedIds = ref([])

  const allSelected = computed(() => {
    const list = unref(itemsRef) || []
    return list.length > 0 && selectedIds.value.length === list.length
  })

  function toggleSelectAll() {
    const list = unref(itemsRef) || []
    selectedIds.value = allSelected.value ? [] : list.map((row) => row.id)
  }

  function toggleSelect(id) {
    selectedIds.value = selectedIds.value.includes(id)
      ? selectedIds.value.filter((i) => i !== id)
      : [...selectedIds.value, id]
  }

  function clearSelection() {
    selectedIds.value = []
  }

  return { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection }
}
