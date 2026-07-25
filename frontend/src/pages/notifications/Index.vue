<script setup>
import { ref, onMounted } from 'vue'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
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
  <v-container fluid class="pa-0" style="max-width: 720px">
      <AppPageHeader
        title="Notifications"
        subtitle="Recent activity relevant to you"
        :actions="[{ label: 'Mark all as read', variant: 'text', onClick: markAllRead }]"
      />

      <v-card rounded="lg" variant="flat" border>
        <template v-for="(n, i) in notifications" :key="n.id">
          <v-divider v-if="i > 0" />
          <v-sheet :color="!n.is_read ? 'primary' : undefined" :variant="!n.is_read ? 'tonal' : 'flat'" class="pa-4 d-flex align-start justify-space-between ga-4">
            <div>
              <p class="text-body-2" :class="{ 'font-weight-semibold': !n.is_read }">{{ n.message }}</p>
              <p class="text-caption text-medium-emphasis mt-1">{{ new Date(n.created_at).toLocaleString() }}</p>
            </div>
            <v-btn v-if="!n.is_read" icon="mdi-check" size="small" variant="text" color="primary" title="Mark read" class="flex-shrink-0" @click="markRead(n)" />
          </v-sheet>
        </template>
        <p v-if="!loading && !notifications.length" class="pa-8 text-center text-body-2 text-medium-emphasis mb-0">No notifications yet.</p>
      </v-card>
  </v-container>
</template>
