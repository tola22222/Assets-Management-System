<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import http from '../../api/http'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const router = useRouter()
const toast = useToastStore()
const count = ref(0)
let timer = null

// Highest notification id already seen. The first check only records it, so
// opening the app doesn't replay the backlog as a burst of toasts — only
// notifications that arrive while the app is open pop up.
let lastSeenId = null
let checking = false

async function check() {
  if (checking) return
  checking = true
  try {
    const { data } = await http.get('/notifications/unread-count')
    const previous = count.value
    count.value = data.count

    // Only fetch the list when the unread count grew (or on the first check,
    // to set the baseline) — the cheap count call is the regular poll.
    if (lastSeenId === null || data.count > previous) {
      const { data: page } = await http.get('/notifications')
      const items = page.data || []
      const newest = items.reduce((max, n) => Math.max(max, n.id), 0)
      if (lastSeenId !== null) {
        items
          .filter((n) => n.id > lastSeenId && !n.is_read)
          .sort((a, b) => a.id - b.id)
          .slice(-3) // a bulk action can create many; show the latest few
          .forEach((n) => toast.info(n.message, t('notifications.new_title')))
      }
      lastSeenId = Math.max(lastSeenId ?? 0, newest)
    }
  } catch {
    // ignore transient failures; the next poll retries
  } finally {
    checking = false
  }
}

// Saves elsewhere in the app announce themselves (see api/http.js), so a
// notification caused by your own action shows up right away rather than on
// the next poll.
function onRefresh() {
  check()
}
function onVisible() {
  if (document.visibilityState === 'visible') check()
}

onMounted(() => {
  check()
  timer = setInterval(check, 12000)
  window.addEventListener('notifications:refresh', onRefresh)
  document.addEventListener('visibilitychange', onVisible)
})
onUnmounted(() => {
  clearInterval(timer)
  window.removeEventListener('notifications:refresh', onRefresh)
  document.removeEventListener('visibilitychange', onVisible)
})
</script>

<template>
  <button @click="router.push('/notifications')" class="relative w-9 h-9 rounded-xl flex items-center justify-center text-muted hover:bg-surface-2 hover:text-fg transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    <span v-if="count > 0" class="absolute top-1 right-1 bg-red-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
      {{ count > 9 ? '9+' : count }}
    </span>
  </button>
</template>
