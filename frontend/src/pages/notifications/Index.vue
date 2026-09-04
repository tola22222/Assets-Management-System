<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const toast = useToastStore()
const notifications = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await http.get('/notifications')
    notifications.value = data.data
  } catch (e) {
    notifications.value = []
    toast.error(errorMessage(e, t('notifications.load_failed')))
  } finally {
    loading.value = false
  }
}

async function markRead(n) {
  try {
    await http.post(`/notifications/${n.id}/mark-read`)
    n.is_read = true
  } catch (e) {
    // Only flip the dot once the server agrees, or the row lies about itself.
    toast.error(errorMessage(e, t('notifications.mark_failed')))
  }
}

async function markAllRead() {
  try {
    await http.post('/notifications/mark-all-read')
    toast.success(t('notifications.marked_all_read'))
    await load()
  } catch (e) {
    toast.error(errorMessage(e, t('notifications.mark_failed')))
  }
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-3xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="font-display text-xl font-bold text-fg tracking-tight">{{ t('notifications.title') }}</h1>
          <p class="text-muted text-sm mt-0.5">{{ t('notifications.subtitle') }}</p>
        </div>
        <button @click="markAllRead" class="text-sm font-semibold text-brand-600 dark:text-brand-300 hover:underline">{{ t('common.mark_all_read') }}</button>
      </div>

      <div class="card divide-y divide-line">
        <div v-for="n in notifications" :key="n.id" class="p-4 flex items-start justify-between gap-4" :class="!n.is_read && 'bg-brand-50/40'">
          <div>
            <p class="text-sm text-fg" :class="!n.is_read && 'font-semibold'">{{ n.message }}</p>
            <p class="text-xs text-faint mt-0.5">{{ new Date(n.created_at).toLocaleString() }}</p>
          </div>
          <button v-if="!n.is_read" @click="markRead(n)" :title="t('common.mark_read')" class="btn-icon">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
          </button>
        </div>
        <p v-if="!loading && !notifications.length" class="p-8 text-center text-faint text-sm">{{ t('notifications.empty') }}</p>
      </div>
    </div>
  </AppLayout>
</template>
