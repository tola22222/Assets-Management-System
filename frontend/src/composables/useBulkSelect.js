import { ref, computed, unref } from 'vue'

// "Select all" checkbox state over a list ref — the rows the header checkbox
// answers for. Pass the page being shown and it ticks only that page (rows
// ticked on other pages stay ticked); pass the whole filtered list and it
// ticks every match.
export function useBulkSelect(itemsRef) {
  const selectedIds = ref([])

  const allSelected = computed(() => {
    const list = unref(itemsRef) || []
    return list.length > 0 && list.every((row) => selectedIds.value.includes(row.id))
  })

  function toggleSelectAll() {
    const list = unref(itemsRef) || []
    const ids = list.map((row) => row.id)
    selectedIds.value = allSelected.value
      ? selectedIds.value.filter((id) => !ids.includes(id))
      : [...new Set([...selectedIds.value, ...ids])]
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
