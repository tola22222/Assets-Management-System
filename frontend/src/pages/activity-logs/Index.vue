<script setup>
import { computed, onMounted } from 'vue'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useServerTable } from '../../composables/useServerTable'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const {
  items: logs, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/activity-logs')

async function removeLog(id) {
  await http.delete(`/activity-logs/${id}`)
  toast.success('Activity log entry deleted.')
  await fetchPage()
}

const headers = computed(() => [
  { title: 'User', key: 'user', sortable: false },
  { title: 'Action', key: 'action', sortable: true },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 70 },
])

onMounted(fetchPage)
</script>

<template>
  <v-container fluid class="pa-0">
      <AppPageHeader title="Activity Logs" subtitle="Full audit trail of actions taken in the system" />

      <AppDataTable
        :headers="headers"
        :items="logs"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        empty-text="No activity recorded yet."
        :show-view="false"
        :show-edit="false"
        @update:search="setSearch"
        @update:options="handleOptions"
        @delete="(row) => removeLog(row.id)"
      >
        <template #item.user="{ item }"><span class="font-medium">{{ item.user?.name || 'System' }}</span></template>
        <template #item.created_at="{ item }">{{ new Date(item.created_at).toLocaleString() }}</template>
      </AppDataTable>
  </v-container>
</template>
