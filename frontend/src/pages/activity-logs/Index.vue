<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useTableSort } from '../../composables/useTableSort'
import { useBulkSelect } from '../../composables/useBulkSelect'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const toast = useToastStore()
const logs = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const confirmingBulkDelete = ref(false)

// Server-paginated (unlike every other list in the app) — this sort only
// reorders the currently loaded page, not the full history.
const { sortKey, sortDir, toggleSort, sorted: sortedLogs } = useTableSort(logs, {
  defaultKey: 'created_at', defaultDir: 'desc',
  paths: { user: 'user.name' },
})
// "Select all" only ever covers the rows currently loaded on this page —
// the list is server-paginated, so there's no full-history selection.
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(logs)

async function loadPage(page = 1) {
  loading.value = true
  clearSelection()
  try {
    const { data } = await http.get('/activity-logs', { params: { page } })
    logs.value = data.data
    currentPage.value = data.current_page
    lastPage.value = data.last_page
  } catch (e) {
    logs.value = []
    toast.error(errorMessage(e, t('activity_logs.load_failed')))
  } finally {
    loading.value = false
  }
}

async function removeLog(id) {
  try {
    await http.delete(`/activity-logs/${id}`)
    toast.success(t('activity_logs.deleted'))
    await loadPage(currentPage.value)
  } catch (e) {
    toast.error(errorMessage(e, t('activity_logs.delete_failed')))
  }
}

async function confirmBulkDelete() {
  confirmingBulkDelete.value = false
  const ids = selectedIds.value
  // allSettled, not all(): one refused row used to abort the whole batch and
  // report nothing, leaving the user unsure which entries actually went.
  const results = await Promise.allSettled(ids.map((id) => http.delete(`/activity-logs/${id}`)))
  const failed = results.filter((r) => r.status === 'rejected')
  const removed = ids.length - failed.length

  if (removed > 0) {
    toast.success(removed === 1
      ? t('activity_logs.bulk_deleted_one')
      : t('activity_logs.bulk_deleted_other', { count: removed }))
  }
  if (failed.length > 0) {
    toast.error(errorMessage(failed[0].reason, t('common.bulk_delete_failed', { count: failed.length })))
  }
  await loadPage(currentPage.value)
}

onMounted(() => loadPage(1))
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('activity_logs.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('activity_logs.subtitle') }}</p>
          </div>
          <button v-if="selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
            {{ t('activity_logs.delete_selected') }} ({{ selectedIds.length }})
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('user')">{{ t('activity_logs.user_col') }}<TableSortIcon :active="sortKey === 'user'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('action')">{{ t('activity_logs.action_col') }}<TableSortIcon :active="sortKey === 'action'" :direction="sortDir" /></th>
                <th>{{ t('common.description') }}</th>
                <th class="th-sort" @click="toggleSort('created_at')">{{ t('common.date') }}<TableSortIcon :active="sortKey === 'created_at'" :direction="sortDir" /></th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in sortedLogs" :key="log.id">
                <td>
                  <input type="checkbox" :checked="selectedIds.includes(log.id)" @change="toggleSelect(log.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="font-medium text-fg">{{ log.user?.name || t('activity_logs.system_user') }}</td>
                <td>{{ log.action }}</td>
                <td>{{ log.description }}</td>
                <td>{{ new Date(log.created_at).toLocaleString() }}</td>
                <td class="text-right">
                  <button @click="removeLog(log.id)" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="!loading && !logs.length">
                <td colspan="6" class="py-10 text-center text-faint">{{ t('activity_logs.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <ConfirmDialog
        v-if="confirmingBulkDelete"
        :title="selectedIds.length === 1 ? t('activity_logs.delete_confirm_title_one') : t('activity_logs.delete_confirm_title_other', { count: selectedIds.length })"
        :message="t('activity_logs.delete_confirm_message')"
        @confirm="confirmBulkDelete"
        @cancel="confirmingBulkDelete = false"
      />

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
