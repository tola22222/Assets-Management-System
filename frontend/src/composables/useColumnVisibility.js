import { ref, watch } from 'vue'

// Show/hide table columns, persisted per-table to localStorage.
export function useColumnVisibility(storageKey, allKeys, defaultHidden = []) {
  let stored = null
  try {
    stored = JSON.parse(localStorage.getItem(storageKey) || 'null')
  } catch {
    stored = null
  }
  const isValid = Array.isArray(stored) && stored.every((k) => allKeys.includes(k))
  const visible = ref(isValid ? stored : allKeys.filter((k) => !defaultHidden.includes(k)))

  watch(visible, (v) => localStorage.setItem(storageKey, JSON.stringify(v)), { deep: true })

  function isVisible(key) {
    return visible.value.includes(key)
  }

  function toggle(key) {
    visible.value = isVisible(key) ? visible.value.filter((k) => k !== key) : [...visible.value, key]
  }

  function reset() {
    visible.value = allKeys.filter((k) => !defaultHidden.includes(k))
  }

  return { visible, isVisible, toggle, reset }
}
