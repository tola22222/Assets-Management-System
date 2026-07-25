<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: locations, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/locations', { defaultSort: 'name', defaultDir: 'asc', filterKeys: ['type'] })
const { create, update, destroy } = useApiCrud('/locations', { entityName: t('locations.entity'), refetch: fetchPage })
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
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

async function confirmDelete() {
  try {
    await destroy(deletingId.value)
  } catch (e) {
    toast.error(e.response?.data?.message || t('locations.delete_failed'))
  } finally {
    deletingId.value = null
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
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader :title="t('locations.title')" :subtitle="t('locations.subtitle')" :buttonText="t('locations.new')" @action="openCreate" />

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
        @delete="(row) => (deletingId = row.id)"
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

        <template #item.type="{ item }"><span class="capitalize">{{ item.type }}</span></template>
        <template #item.assets_count="{ item }">{{ item.assets_count ?? 0 }}</template>
      </AppDataTable>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('locations.edit_title') : t('locations.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('locations.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('locations.type_required') }}</label>
            <select v-model="form.type" class="input">
              <option value="office">{{ t('locations.type_office') }}</option>
              <option value="lab">{{ t('locations.type_lab') }}</option>
              <option value="program">{{ t('locations.type_program') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.description') }}</label>
            <textarea v-model="form.description" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('locations.save_changes') : t('locations.create_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
