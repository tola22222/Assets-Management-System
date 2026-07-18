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
          <h1 class="text-xl font-bold text-ink tracking-tight">Notifications</h1>
          <p class="text-gray-500 text-sm mt-0.5">Recent activity relevant to you</p>
        </div>
        <button @click="markAllRead" class="text-sm font-semibold text-brand hover:underline">Mark all as read</button>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="n in notifications" :key="n.id" class="p-4 flex items-start justify-between gap-4" :class="!n.is_read && 'bg-brand-50/40'">
          <div>
            <p class="text-sm text-ink" :class="!n.is_read && 'font-semibold'">{{ n.message }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ new Date(n.created_at).toLocaleString() }}</p>
          </div>
          <button v-if="!n.is_read" @click="markRead(n)" class="text-xs font-semibold text-brand hover:underline flex-shrink-0">Mark read</button>
        </div>
        <p v-if="!loading && !notifications.length" class="p-8 text-center text-gray-400 text-sm">No notifications yet.</p>
      </div>
    </div>
  </AppLayout>
</template>
