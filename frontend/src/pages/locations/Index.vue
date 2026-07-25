<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
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
const form = reactive({ name: '', type: 'office', description: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', type: 'office', description: '' })
  showModal.value = true
}

function openEdit(location) {
  editingId.value = location.id
  Object.assign(form, { name: location.name, type: location.type, description: location.description || '' })
  showModal.value = true
}

async function handleSubmit() {
  if (editingId.value) {
    await update(editingId.value, form)
  } else {
    await create(form)
  }
  showModal.value = false
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

      <template #item.type="{ item }"><span class="text-capitalize">{{ item.type }}</span></template>
      <template #item.assets_count="{ item }">{{ item.assets_count ?? 0 }}</template>
    </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="editingId ? t('locations.edit_title') : t('locations.create_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-text-field v-model="form.name" :label="t('locations.name_required')" required />
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
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">
          {{ editingId ? t('locations.save_changes') : t('locations.create_button') }}
        </v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
