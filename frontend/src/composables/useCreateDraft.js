import { watch } from 'vue'

// Remembers what was typed into a Create form, so closing the dialog by
// accident (the X, Cancel, a click outside, Esc, even a page refresh) does not
// lose it: opening the Create form again brings the values back.
//
// Nothing is saved as a draft on the server and nothing is POSTed — the
// values live in this browser tab only (sessionStorage), and are cleared once
// the record is really created, or when the tab is closed.
//
//   const draft = useCreateDraft('suppliers', form, () => showModal.value && !editingId.value)
//   openCreate():  reset the form to empty, then draft.restore()
//   after a successful create:  draft.clear()
//
// `isCreating` keeps an Edit form from ever being remembered as a new record.
// Chosen files (photos) cannot be kept; `exclude` names fields that must not
// be (a password).
export function useCreateDraft(key, form, isCreating, { exclude = [] } = {}) {
  const storageKey = 'create-draft:' + key
  // After a successful save nothing is remembered until the form is opened again.
  let paused = false

  function read() {
    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey))
      return saved && typeof saved === 'object' ? saved : null
    } catch {
      return null
    }
  }

  watch(form, (values) => {
    if (paused || !isCreating()) return
    const copy = { ...values }
    exclude.forEach((field) => delete copy[field])
    try {
      sessionStorage.setItem(storageKey, JSON.stringify(copy))
    } catch {
      // Storage blocked or full: the form simply isn't remembered.
    }
  }, { deep: true })

  return {
    // Call right after resetting the form to its empty values.
    restore() {
      paused = false
      const saved = read()
      if (!saved) return
      // Only fields the form still has, so an old draft can't add stray keys.
      Object.keys(form).forEach((field) => {
        if (field in saved && !exclude.includes(field)) form[field] = saved[field]
      })
    },
    clear() {
      paused = true
      try {
        sessionStorage.removeItem(storageKey)
      } catch {
        // Nothing to clear.
      }
    },
  }
}
