<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { ref } from 'vue'

const { t } = useI18n()
const { items: movements, loading, fetchAll, destroy } = useApiCrud('/asset-movements', { entityName: t('asset_movements.title') })
const deletingId = ref(null)

function formatDate(v) {
  return v ? new Date(v).toLocaleString() : t('common.n_a')
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

onMounted(fetchAll)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_movements.title')" :subtitle="t('asset_movements.subtitle')" />

      <div class="bg-surface rounded-2xl border border-line overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-faint font-semibold bg-surface-2/70 border-b border-line">
              <th class="p-4 pl-5">{{ t('common.asset') }}</th>
              <th class="p-4">{{ t('asset_movements.type') }}</th>
              <th class="p-4">{{ t('asset_movements.from') }}</th>
              <th class="p-4">{{ t('asset_movements.to') }}</th>
              <th class="p-4">{{ t('asset_movements.reference') }}</th>
              <th class="p-4">{{ t('asset_movements.date') }}</th>
              <th class="p-4 pr-5 text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="m in movements" :key="m.id" class="hover:bg-surface-2/50">
              <td class="p-4 pl-5">
                <p class="font-medium text-fg">{{ m.asset?.name || t('common.n_a') }}</p>
                <p class="font-mono text-xs text-faint">{{ m.asset?.asset_code }}</p>
              </td>
              <td class="p-4"><StatusBadge :status="m.movement_type" /></td>
              <td class="p-4 text-muted">{{ m.from_location?.name || '—' }}</td>
              <td class="p-4 text-muted">{{ m.to_location?.name || t('common.n_a') }}</td>
              <td class="p-4 text-muted font-mono text-xs">{{ m.reference_no || '—' }}</td>
              <td class="p-4 text-muted">{{ formatDate(m.created_at) }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="deletingId = m.id" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </td>
            </tr>
            <tr v-if="!loading && !movements.length">
              <td colspan="7" class="p-8 text-center text-faint">{{ t('asset_movements.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ConfirmDialog v-if="deletingId" :title="t('asset_movements.delete_confirm_title')" :message="t('asset_movements.delete_confirm_message')" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
