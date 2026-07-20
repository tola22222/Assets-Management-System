<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const { items: returnsList, loading, fetchAll } = useApiCrud('/asset-returns', { entityName: t('asset_returns.entity') })
const toast = useToastStore()
const auth = useAuthStore()

const assignments = ref([])
const showModal = ref(false)
const form = reactive({ assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: '' })

async function loadOptions() {
  const { data } = await http.get('/asset-assignments')
  assignments.value = data.filter((a) => a.status !== 'returned')
}

function openCreate() {
  Object.assign(form, { assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: new Date().toISOString().slice(0, 10) })
  showModal.value = true
}

function onAssignmentChange() {
  const assignment = assignments.value.find((a) => a.id == form.assignment_id)
  form.asset_id = assignment?.asset_id || ''
}

async function handleSubmit() {
  try {
    await http.post('/asset-returns', form)
    toast.success(t('asset_returns.submitted'))
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_returns.submit_failed'))
  }
}

async function approve(id) {
  await http.post(`/asset-returns/${id}/approve`)
  toast.success(t('asset_returns.approved'))
  await fetchAll()
}

async function reject(id) {
  await http.post(`/asset-returns/${id}/reject`)
  toast.success(t('asset_returns.rejected'))
  await fetchAll()
}

onMounted(() => {
  fetchAll()
  loadOptions()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_returns.title')" :subtitle="t('asset_returns.subtitle')" :buttonText="t('asset_returns.new')" @action="openCreate" />

      <div class="bg-surface rounded-2xl border border-line overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-faint font-semibold bg-surface-2/70 border-b border-line">
              <th class="p-4 pl-5">{{ t('common.asset') }}</th>
              <th class="p-4">{{ t('asset_returns.condition') }}</th>
              <th class="p-4">{{ t('asset_returns.returned_by') }}</th>
              <th class="p-4">{{ t('common.status') }}</th>
              <th class="p-4 pr-5 text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="r in returnsList" :key="r.id" class="hover:bg-surface-2/50">
              <td class="p-4 pl-5 font-medium text-fg">{{ r.asset?.name || t('common.n_a') }}</td>
              <td class="p-4 text-muted capitalize">{{ r.condition }}</td>
              <td class="p-4 text-muted">{{ r.returned_by?.name || t('common.n_a') }}</td>
              <td class="p-4"><StatusBadge :status="r.status" /></td>
              <td class="p-4 pr-5 text-right whitespace-nowrap">
                <template v-if="r.status === 'pending' && auth.user?.role === 'admin'">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="approve(r.id)" :title="t('common.approve')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </button>
                    <button @click="reject(r.id)" :title="t('common.reject')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </button>
                  </div>
                </template>
              </td>
            </tr>
            <tr v-if="!loading && !returnsList.length">
              <td colspan="5" class="p-8 text-center text-faint">{{ t('asset_returns.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_returns.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.assignment_required') }}</label>
          <select v-model="form.assignment_id" required @change="onAssignmentChange" class="input">
            <option value="">{{ t('asset_returns.select_assignment') }}</option>
            <option v-for="a in assignments" :key="a.id" :value="a.id">{{ a.asset?.name }} — {{ a.recipient_name }}</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.condition_required') }}</label>
          <select v-model="form.condition" class="input">
            <option value="good">{{ t('asset_returns.condition_good') }}</option>
            <option value="fair">{{ t('asset_returns.condition_fair') }}</option>
            <option value="broken">{{ t('asset_returns.condition_broken') }}</option>
            <option value="lost">{{ t('asset_returns.condition_lost') }}</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.return_date_required') }}</label>
          <input v-model="form.return_date" type="date" required class="input" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.damage_notes') }}</label>
          <textarea v-model="form.damage_notes" rows="2" class="input"></textarea>
        </div>
        <button type="submit" class="btn-primary w-full">{{ t('asset_returns.submit_button') }}</button>
      </form>
    </Modal>
  </AppLayout>
</template>
