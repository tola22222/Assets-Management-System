<script setup>
import { ref, onMounted } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const logs = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const loading = ref(true)

async function loadPage(page = 1) {
  loading.value = true
  const { data } = await http.get('/activity-logs', { params: { page } })
  logs.value = data.data
  currentPage.value = data.current_page
  lastPage.value = data.last_page
  loading.value = false
}

async function removeLog(id) {
  await http.delete(`/activity-logs/${id}`)
  toast.success('Activity log entry deleted.')
  await loadPage(currentPage.value)
}

onMounted(() => loadPage(1))
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <div>
        <h1 class="text-xl font-bold text-ink tracking-tight">Activity Logs</h1>
        <p class="text-gray-500 text-sm mt-0.5">Full audit trail of actions taken in the system</p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">User</th>
              <th class="p-4">Action</th>
              <th class="p-4">Description</th>
              <th class="p-4">Date</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ log.user?.name || 'System' }}</td>
              <td class="p-4 text-gray-500">{{ log.action }}</td>
              <td class="p-4 text-gray-500">{{ log.description }}</td>
              <td class="p-4 text-gray-500">{{ new Date(log.created_at).toLocaleString() }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="removeLog(log.id)" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !logs.length">
              <td colspan="5" class="p-8 text-center text-gray-400">No activity recorded yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="lastPage > 1" class="flex items-center justify-center gap-2">
        <button v-for="p in lastPage" :key="p" @click="loadPage(p)"
          class="w-8 h-8 rounded-lg text-sm font-semibold transition"
          :class="p === currentPage ? 'bg-brand text-white' : 'text-gray-500 hover:bg-gray-100'">
          {{ p }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
