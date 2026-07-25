<script setup>
import { ref, h, onMounted } from 'vue'
import { NDataTable } from 'naive-ui'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const logs = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(20)
const loading = ref(true)

async function loadPage(page = 1) {
  loading.value = true
  const { data } = await http.get('/activity-logs', { params: { page } })
  logs.value = data.data
  currentPage.value = data.current_page
  lastPage.value = data.last_page
  total.value = data.total
  perPage.value = data.per_page
  loading.value = false
}

async function removeLog(id) {
  await http.delete(`/activity-logs/${id}`)
  toast.success('Activity log entry deleted.')
  await loadPage(currentPage.value)
}

const columns = [
  {
    title: 'User',
    key: 'user',
    render: (log) => h('span', { class: 'font-medium text-fg' }, log.user?.name || 'System'),
  },
  {
    title: 'Action',
    key: 'action',
    render: (log) => log.action,
  },
  {
    title: 'Description',
    key: 'description',
    render: (log) => log.description,
  },
  {
    title: 'Date',
    key: 'created_at',
    render: (log) => new Date(log.created_at).toLocaleString(),
  },
  {
    title: 'Actions',
    key: 'actions',
    render: (log) => h('div', { class: 'flex justify-end' }, [
      h('button', {
        onClick: () => removeLog(log.id),
        title: 'Delete',
        class: 'w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition',
      }, [
        h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.2', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0' }),
        ]),
      ]),
    ]),
  },
]

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
        <n-data-table
          :columns="columns"
          :data="logs"
          :loading="loading"
          :row-key="(row) => row.id"
          :remote="true"
          :pagination="{
            page: currentPage,
            pageSize: perPage,
            pageCount: lastPage,
            itemCount: total,
            onUpdatePage: (p) => loadPage(p),
          }"
          :bordered="false"
        >
          <template #empty>
            <p class="py-10 text-center text-faint text-sm">No activity recorded yet.</p>
          </template>
        </n-data-table>
      </div>
    </div>
  </AppLayout>
</template>
