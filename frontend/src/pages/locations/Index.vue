<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import PepyDialog from '../../components/ui/PepyDialog.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: locations, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/locations', { defaultSort: 'name', defaultDir: 'asc', filterKeys: ['type'] })
const { create, update, destroy } = useApiCrud('/locations', { entityName: t('locations.entity'), refetch: fetchPage })
const { confirm } = useConfirm()
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const form = reactive({ name: '', code: '', type: 'office', description: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', code: '', type: 'office', description: '' })
  showModal.value = true
}

function openEdit(location) {
  editingId.value = location.id
  Object.assign(form, { name: location.name, code: location.code || '', type: location.type, description: location.description || '' })
  showModal.value = true
}

async function handleSubmit() {
  submitting.value = true
  try {
    if (editingId.value) {
      await update(editingId.value, form)
    } else {
      await create(form)
    }
    showModal.value = false
  } catch (e) {
    toast.error(e.response?.data?.message || t('locations.save_failed'))
  } finally {
    submitting.value = false
  }
}

async function handleDelete(row) {
  const ok = await confirm({
    title: t('confirm.delete_title'),
    message: t('confirm.delete_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  try {
    await destroy(row.id)
  } catch (e) {
    toast.error(e.response?.data?.message || t('locations.delete_failed'))
  }
}

const headers = computed(() => [
  { title: t('common.name'), key: 'name', sortable: true },
  { title: t('locations.code'), key: 'code', sortable: true },
  { title: t('locations.type'), key: 'type', sortable: true },
  { title: t('locations.stock_records'), key: 'assets_count', sortable: false, align: 'end' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 110 },
])

onMounted(fetchPage)
</script>

<template>
  <v-container fluid class="pa-0">
    <AppPageHeader
      :title="t('locations.title')"
      :subtitle="t('locations.subtitle')"
      :actions="[{ label: t('locations.new'), icon: 'mdi-plus', onClick: openCreate }]"
    />

    <AppDataTable
      :headers="headers"
      :items="locations"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="sortByVuetify"
      :search="search"
      :search-label="t('locations.search_placeholder')"
      :empty-text="search ? t('locations.empty_search') : t('locations.empty')"
      @update:search="setSearch"
      @update:options="handleOptions"
      @edit="openEdit"
      @delete="handleDelete"
    >
      <template #filters>
        <v-select
          v-model="filters.type"
          :label="t('locations.type')"
          :items="[
            { title: t('common.all'), value: '' },
            { title: t('locations.type_office'), value: 'office' },
            { title: t('locations.type_lab'), value: 'lab' },
            { title: t('locations.type_program'), value: 'program' },
          ]"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 220px"
          @update:model-value="applyFilters"
        />
        <v-btn v-if="hasActiveFilters" variant="text" size="small" @click="clearFilters">{{ t('common.clear_filters') }}</v-btn>
      </template>

      <template #item.code="{ item }"><span class="font-mono font-mono-tag text-caption">{{ item.code || '—' }}</span></template>
      <template #item.type="{ item }"><span class="text-capitalize">{{ item.type }}</span></template>
      <template #item.assets_count="{ item }">{{ item.assets_count ?? 0 }}</template>
    </AppDataTable>
  </v-container>

  <PepyDialog
    v-if="showModal"
    :title="editingId ? t('locations.edit_title') : t('locations.create_title')"
    icon="mdi-map-marker-outline"
    :loading="submitting"
    :confirm-text="editingId ? t('locations.save_changes') : t('locations.create_button')"
    @update:model-value="(v) => !v && (showModal = false)"
    @confirm="handleSubmit"
  >
    <v-row dense>
      <v-col cols="12" sm="8">
        <v-text-field v-model="form.name" :label="t('locations.name_required')" required />
      </v-col>
      <v-col cols="12" sm="4">
        <v-text-field v-model="form.code" :label="t('locations.code')" maxlength="4" class="text-uppercase" />
      </v-col>
    </v-row>
    <v-select
      v-model="form.type"
      :label="t('locations.type_required')"
      :items="[
        { title: t('locations.type_office'), value: 'office' },
        { title: t('locations.type_lab'), value: 'lab' },
        { title: t('locations.type_program'), value: 'program' },
      ]"
    />
    <v-textarea v-model="form.description" :label="t('common.description')" rows="2" />
  </PepyDialog>
</template>
