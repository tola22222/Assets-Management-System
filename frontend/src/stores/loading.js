import { defineStore } from 'pinia'

// Counter-based rather than boolean: concurrent requests each call show()/hide(),
// and the overlay must stay visible until the LAST one finishes.
export const useLoadingStore = defineStore('loading', {
  state: () => ({
    count: 0,
    message: '',
  }),
  getters: {
    active: (state) => state.count > 0,
  },
  actions: {
    show(message = '') {
      this.count += 1
      if (message) this.message = message
    },
    hide() {
      this.count = Math.max(0, this.count - 1)
      if (this.count === 0) this.message = ''
    },
    reset() {
      this.count = 0
      this.message = ''
    },
  },
})
