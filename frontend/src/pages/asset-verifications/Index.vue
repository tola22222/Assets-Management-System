<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useServerTable } from '../../composables/useServerTable'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const toast = useToastStore()
const auth = useAuthStore()

const {
  items: verifications, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/asset-verifications', { filterKeys: ['condition', 'completed'] })

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const form = reactive({ asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })

const headers = computed(() => [
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('common.location'), key: 'location', sortable: false },
  { title: t('asset_returns.condition'), key: 'condition', sortable: true },
  { title: t('asset_verifications.verified_by'), key: 'verified_by', sortable: false },
  { title: t('common.status'), key: 'verified_at', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 70 },
])

async function loadOptions() {
  const [a, l] = await Promise.all([http.get('/assets/export'), http.get('/locations', { params: { per_page: 100 } })])
  assets.value = a.data.data
  locations.value = l.data.data ?? l.data
}

function openCreate() {
  Object.assign(form, { asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-verifications', form)
    toast.success(t('asset_verifications.recorded'))
    showModal.value = false
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_verifications.record_failed'))
  }
}

async function complete(id) {
  await http.post(`/asset-verifications/${id}/complete`)
  toast.success(t('asset_verifications.marked_complete'))
  await fetchPage()
}

onMounted(() => {
  fetchPage()
  loadOptions()
})
</script>

<template>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_verifications.title')" :subtitle="t('asset_verifications.subtitle')" :buttonText="t('asset_verifications.new')" @action="openCreate" />

      <AppDataTable
        :headers="headers"
        :items="verifications"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :empty-text="t('asset_verifications.empty')"
        @update:search="setSearch"
        @update:options="handleOptions"
      >
        <template #filters>
          <v-select
            v-model="filters.condition"
            :label="t('asset_returns.condition')"
            :items="[
              { title: t('common.all'), value: '' },
              { title: t('asset_verifications.condition_good'), value: 'good' },
              { title: t('asset_verifications.condition_fair'), value: 'fair' },
              { title: t('asset_verifications.condition_broken'), value: 'broken' },
              { title: t('asset_verifications.condition_lost'), value: 'lost' },
            ]"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 220px"
            @update:model-value="applyFilters"
          />
          <v-select
            v-model="filters.completed"
            :label="t('common.status')"
            :items="[
              { title: t('common.all'), value: '' },
              { title: t('asset_verifications.complete'), value: 'yes' },
              { title: t('asset_verifications.pending'), value: 'no' },
            ]"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 220px"
            @update:model-value="applyFilters"
          />
          <v-btn v-if="hasActiveFilters" variant="text" size="small" @click="clearFilters">{{ t('common.clear_filters') }}</v-btn>
        </template>

        <template #item.asset="{ item }"><span class="font-medium text-fg">{{ item.asset?.name || t('common.n_a') }}</span></template>
        <template #item.location="{ item }">{{ item.location?.name || t('common.n_a') }}</template>
        <template #item.condition="{ item }"><span class="capitalize">{{ item.condition }}</span></template>
        <template #item.verified_by="{ item }">{{ item.verified_by?.name || t('common.n_a') }}</template>
        <template #item.verified_at="{ item }">
          <span class="px-2.5 py-1 rounded-lg text-xs font-bold" :class="item.verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
            {{ item.verified_at ? t('asset_verifications.complete') : t('asset_verifications.pending') }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <div v-if="!item.verified_at && auth.canCompleteVerification" class="flex justify-end">
            <button @click="complete(item.id)" :title="t('common.mark_complete')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </button>
          </div>
        </template>
      </AppDataTable>
    </div>

    <Modal v-if="showModal" :title="t('asset_verifications.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.location_required') }}</label>
              <select v-model="form.location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.quantity_verified_required') }}</label>
              <input v-model.number="form.quantity_verified" type="number" min="1" required class="input" />
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.condition_required') }}</label>
            <select v-model="form.condition" class="input">
              <option value="good">{{ t('asset_verifications.condition_good') }}</option>
              <option value="fair">{{ t('asset_verifications.condition_fair') }}</option>
              <option value="broken">{{ t('asset_verifications.condition_broken') }}</option>
              <option value="lost">{{ t('asset_verifications.condition_lost') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.remark') }}</label>
            <textarea v-model="form.remark" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_verifications.submit_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>
</template>
