import { defineStore } from 'pinia'

let nextId = 1
const DEFAULT_TIMEOUT = 4000

// Queue of Vuetify v-snackbar messages. NotificationSnackbar.vue shows one at a
// time and advances the queue on close/timeout, so bursts of notifications don't
// overlap each other.
export const useNotificationStore = defineStore('notification', {
  state: () => ({
    queue: [],
  }),
  actions: {
    push({ type = 'info', message, timeout = DEFAULT_TIMEOUT, action = null }) {
      const id = nextId++
      this.queue.push({ id, type, message, timeout, action })
      return id
    },
    success(message, options = {}) {
      return this.push({ type: 'success', message, ...options })
    },
    error(message, options = {}) {
      return this.push({ type: 'error', message, timeout: 6000, ...options })
    },
    warning(message, options = {}) {
      return this.push({ type: 'warning', message, ...options })
    },
    info(message, options = {}) {
      return this.push({ type: 'info', message, ...options })
    },
    dismiss(id) {
      this.queue = this.queue.filter((n) => n.id !== id)
    },
    clear() {
      this.queue = []
    },
  },
})
