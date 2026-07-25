import { defineStore } from 'pinia'

const DEFAULTS = {
  title: 'Are you sure?',
  message: '',
  icon: null,
  color: 'primary',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
}

// Single global confirm dialog. `open()` stores the resolver on the store itself
// (not component state) so ConfirmDialog.vue only has to read state and call
// resolve/reject — this is what lets `await $confirm(...)` work from anywhere,
// including outside components (e.g. a composable or an axios interceptor).
export const useConfirmStore = defineStore('confirm', {
  state: () => ({
    visible: false,
    options: { ...DEFAULTS },
    _resolve: null,
  }),
  actions: {
    open(options = {}) {
      this.options = { ...DEFAULTS, ...options }
      this.visible = true
      return new Promise((resolve) => {
        this._resolve = resolve
      })
    },
    confirm() {
      this.visible = false
      this._resolve?.(true)
      this._resolve = null
    },
    cancel() {
      this.visible = false
      this._resolve?.(false)
      this._resolve = null
    },
  },
})
