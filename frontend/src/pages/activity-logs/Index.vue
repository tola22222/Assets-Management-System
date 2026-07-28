<script setup>
import { ref, onMounted } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const logs = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const loading = ref(true)

// Server-paginated (unlike every other list in the app) — this sort only
// reorders the currently loaded page, not the full history.
const { sortKey, sortDir, toggleSort, sorted: sortedLogs } = useTableSort(logs, {
  defaultKey: 'created_at', defaultDir: 'desc',
  paths: { user: 'user.name' },
})

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
        <h1 class="text-xl font-bold text-fg tracking-tight">Activity Logs</h1>
        <p class="text-muted text-sm mt-0.5">Full audit trail of actions taken in the system</p>
      </div>

      <div class="table-wrap">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="th-sort" @click="toggleSort('user')">User<TableSortIcon :active="sortKey === 'user'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('action')">Action<TableSortIcon :active="sortKey === 'action'" :direction="sortDir" /></th>
                <th>Description</th>
                <th class="th-sort" @click="toggleSort('created_at')">Date<TableSortIcon :active="sortKey === 'created_at'" :direction="sortDir" /></th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in sortedLogs" :key="log.id">
                <td class="font-medium text-fg">{{ log.user?.name || 'System' }}</td>
                <td>{{ log.action }}</td>
                <td>{{ log.description }}</td>
                <td>{{ new Date(log.created_at).toLocaleString() }}</td>
                <td class="text-right">
                  <button @click="removeLog(log.id)" title="Delete" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="!loading && !logs.length">
                <td colspan="5" class="py-10 text-center text-faint">No activity recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="lastPage > 1" class="flex items-center justify-center gap-2">
        <button v-for="p in lastPage" :key="p" @click="loadPage(p)"
          class="w-8 h-8 rounded-lg text-sm font-semibold transition"
          :class="p === currentPage ? 'bg-brand text-white' : 'text-muted hover:bg-surface-3'">
          {{ p }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
