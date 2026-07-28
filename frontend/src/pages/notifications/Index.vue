<script setup>
import { ref, onMounted } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const notifications = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  const { data } = await http.get('/notifications')
  notifications.value = data.data
  loading.value = false
}

async function markRead(n) {
  await http.post(`/notifications/${n.id}/mark-read`)
  n.is_read = true
}

async function markAllRead() {
  await http.post('/notifications/mark-all-read')
  toast.success('All notifications marked as read.')
  await load()
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-3xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-fg tracking-tight">Notifications</h1>
          <p class="text-muted text-sm mt-0.5">Recent activity relevant to you</p>
        </div>
        <button @click="markAllRead" class="text-sm font-semibold text-brand-600 dark:text-brand-300 hover:underline">Mark all as read</button>
      </div>

      <div class="bg-surface rounded-2xl border border-line divide-y divide-line">
        <div v-for="n in notifications" :key="n.id" class="p-4 flex items-start justify-between gap-4" :class="!n.is_read && 'bg-brand-50/40'">
          <div>
            <p class="text-sm text-fg" :class="!n.is_read && 'font-semibold'">{{ n.message }}</p>
            <p class="text-xs text-faint mt-0.5">{{ new Date(n.created_at).toLocaleString() }}</p>
          </div>
          <button v-if="!n.is_read" @click="markRead(n)" title="Mark read" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
          </button>
        </div>
        <p v-if="!loading && !notifications.length" class="p-8 text-center text-faint text-sm">No notifications yet.</p>
      </div>
    </div>
  </AppLayout>
</template>
