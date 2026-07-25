<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
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
  <v-container fluid class="pa-0">
      <AppPageHeader
        :title="t('asset_verifications.title')"
        :subtitle="t('asset_verifications.subtitle')"
        :actions="[{ label: t('asset_verifications.new'), icon: 'mdi-plus', onClick: openCreate }]"
      />

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

        <template #item.asset="{ item }"><span class="font-medium">{{ item.asset?.name || t('common.n_a') }}</span></template>
        <template #item.location="{ item }">{{ item.location?.name || t('common.n_a') }}</template>
        <template #item.condition="{ item }"><span class="text-capitalize">{{ item.condition }}</span></template>
        <template #item.verified_by="{ item }">{{ item.verified_by?.name || t('common.n_a') }}</template>
        <template #item.verified_at="{ item }">
          <v-chip size="small" :color="item.verified_at ? 'success' : 'warning'" variant="tonal">
            {{ item.verified_at ? t('asset_verifications.complete') : t('asset_verifications.pending') }}
          </v-chip>
        </template>

        <template #item.actions="{ item }">
          <div v-if="!item.verified_at && auth.canCompleteVerification" class="d-flex justify-end">
            <v-btn icon="mdi-check" size="small" variant="text" color="success" :title="t('common.mark_complete')" @click="complete(item.id)" />
          </div>
        </template>
      </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="t('asset_verifications.modal_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-select
          v-model="form.asset_id"
          :label="t('asset_verifications.asset_required')"
          :items="assets.map((a) => ({ title: `${a.name} (${a.asset_code})`, value: a.id }))"
          required
        />
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.location_id"
              :label="t('asset_verifications.location_required')"
              :items="locations.map((l) => ({ title: l.name, value: l.id }))"
              required
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model.number="form.quantity_verified" type="number" min="1" :label="t('asset_verifications.quantity_verified_required')" required />
          </v-col>
        </v-row>
        <v-select
          v-model="form.condition"
          :label="t('asset_verifications.condition_required')"
          :items="[
            { title: t('asset_verifications.condition_good'), value: 'good' },
            { title: t('asset_verifications.condition_fair'), value: 'fair' },
            { title: t('asset_verifications.condition_broken'), value: 'broken' },
            { title: t('asset_verifications.condition_lost'), value: 'lost' },
          ]"
        />
        <v-textarea v-model="form.remark" :label="t('asset_verifications.remark')" rows="2" />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">{{ t('asset_verifications.submit_button') }}</v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
