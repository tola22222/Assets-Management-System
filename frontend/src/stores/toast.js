import { defineStore } from 'pinia'

let nextId = 1

// An error is usually longer and needs re-reading; a success confirmation does
// not. Errors and warnings therefore linger, the rest clear quickly.
const LIFETIME = { error: 6000, warning: 6000, success: 4000, info: 4000 }

// Timer handles live outside the store state on purpose: they are not data the
// UI renders, and keeping them out of the reactive object avoids re-rendering
// every toast each time one of them ticks.
const timers = new Map()

function clear(id) {
  const timer = timers.get(id)
  if (timer) clearTimeout(timer.handle)
  timers.delete(id)
}

function schedule(store, id, ms) {
  clear(id)
  timers.set(id, { handle: setTimeout(() => store.dismiss(id), ms), endsAt: Date.now() + ms })
}

export const useToastStore = defineStore('toast', {
  state: () => ({ items: [] }),
  actions: {
    // `title` is optional: ToastHost falls back to a per-type heading from the
    // translations, so the ~110 existing single-argument calls across the app
    // keep working and still render the two-line card.
    push(message, type = 'success', title = null) {
      const id = nextId++
      const duration = LIFETIME[type] ?? 4000
      // `duration` rides along so the card can drive its countdown bar from the
      // same number that actually dismisses it — the two can never drift.
      this.items.push({ id, message, type, title, duration })
      schedule(this, id, duration)
      return id
    },
    success(message, title = null) {
      return this.push(message, 'success', title)
    },
    error(message, title = null) {
      return this.push(message, 'error', title)
    },
    warning(message, title = null) {
      return this.push(message, 'warning', title)
    },
    info(message, title = null) {
      return this.push(message, 'info', title)
    },
    // Hovering holds the toast open: a message must not disappear out from
    // under someone who is still reading it, and the countdown bar pauses in
    // step through the same duration.
    pause(id) {
      const timer = timers.get(id)
      if (!timer) return
      clearTimeout(timer.handle)
      timers.set(id, { handle: null, remaining: Math.max(0, timer.endsAt - Date.now()) })
    },
    resume(id) {
      const timer = timers.get(id)
      if (!timer || timer.handle) return
      schedule(this, id, timer.remaining ?? 0)
    },
    dismiss(id) {
      clear(id)
      this.items = this.items.filter((t) => t.id !== id)
    },
  },
})
