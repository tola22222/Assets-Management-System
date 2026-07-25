<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: staffList, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/staff', { filterKeys: ['status'] })
const { destroy } = useApiCrud('/staff', { entityName: t('staff.entity'), refetch: fetchPage })
const { confirm } = useConfirm()
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const photoFile = ref(null)
const emptyForm = () => ({ full_name: '', email: '', phone: '', position: '', hire_date: '', status: 'active' })
const form = reactive(emptyForm())

const headers = computed(() => [
  { title: t('common.name'), key: 'full_name', sortable: true },
  { title: t('staff.position'), key: 'position', sortable: true },
  { title: t('common.phone'), key: 'phone', sortable: false },
  { title: t('common.status'), key: 'status', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 110 },
])

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  photoFile.value = null
  showModal.value = true
}

function openEdit(staff) {
  editingId.value = staff.id
  Object.assign(form, {
    full_name: staff.full_name, email: staff.email || '', phone: staff.phone || '',
    position: staff.position || '', hire_date: staff.hire_date || '', status: staff.status || 'active',
  })
  photoFile.value = null
  showModal.value = true
}

async function handleSubmit() {
  const fd = new FormData()
  Object.entries(form).forEach(([k, v]) => { if (v !== '') fd.append(k, v) })
  if (photoFile.value) fd.append('photo', photoFile.value)

  try {
    if (editingId.value) {
      fd.append('_method', 'PUT')
      await http.post(`/staff/${editingId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast.success(t('staff.updated'))
    } else {
      await http.post('/staff', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast.success(t('staff.created'))
    }
    showModal.value = false
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('staff.save_failed'))
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
  if (ok) await destroy(row.id)
}

onMounted(fetchPage)
</script>

<template>
  <v-container class="d-flex flex-column ga-6">
    <AppPageHeader
      :title="t('staff.title')"
      :subtitle="t('staff.subtitle')"
      :actions="[{ label: t('staff.new'), icon: 'mdi-plus', onClick: openCreate }]"
    />

    <AppDataTable
      :headers="headers"
      :items="staffList"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="sortByVuetify"
      :search="search"
      :search-label="t('staff.search_placeholder')"
      :empty-text="search ? t('staff.empty_search') : t('staff.empty')"
      @update:search="setSearch"
      @update:options="handleOptions"
      @edit="openEdit"
      @delete="handleDelete"
    >
      <template #filters>
        <v-select
          v-model="filters.status"
          :label="t('common.status')"
          :items="[
            { title: t('common.all'), value: '' },
            { title: t('staff.status_active'), value: 'active' },
            { title: t('staff.status_inactive'), value: 'inactive' },
          ]"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 220px"
          @update:model-value="applyFilters"
        />
        <v-btn v-if="hasActiveFilters" variant="text" size="small" @click="clearFilters">{{ t('common.clear_filters') }}</v-btn>
      </template>

      <template #item.full_name="{ item }">
        <div class="d-flex align-center ga-3">
          <v-avatar size="32">
            <v-img v-if="item.photo_path_url" :src="item.photo_path_url" alt="" />
            <v-icon v-else icon="mdi-account" />
          </v-avatar>
          <span class="font-weight-medium">{{ item.full_name }}</span>
        </div>
      </template>
      <template #item.position="{ item }">{{ item.position || '—' }}</template>
      <template #item.phone="{ item }">{{ item.phone || '—' }}</template>
      <template #item.status="{ item }">
        <v-chip size="small" :color="item.status === 'active' ? 'success' : undefined" variant="tonal">
          {{ t(`status.${item.status}`) }}
        </v-chip>
      </template>
    </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="editingId ? t('staff.edit_title') : t('staff.create_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-text-field v-model="form.full_name" :label="t('staff.full_name')" required />
        <div class="d-flex ga-4">
          <v-text-field v-model="form.email" :label="t('common.email')" type="email" class="flex-grow-1" />
          <v-text-field v-model="form.phone" :label="t('common.phone')" class="flex-grow-1" />
        </div>
        <div class="d-flex ga-4">
          <v-text-field v-model="form.position" :label="t('staff.position')" class="flex-grow-1" />
          <v-text-field v-model="form.hire_date" :label="t('staff.hire_date')" type="date" class="flex-grow-1" />
        </div>
        <v-select
          v-if="editingId"
          v-model="form.status"
          :label="t('staff.status_required')"
          :items="[
            { title: t('staff.status_active'), value: 'active' },
            { title: t('staff.status_inactive'), value: 'inactive' },
          ]"
        />
        <v-file-input v-model="photoFile" :label="t('staff.photo')" accept="image/jpeg,image/png" prepend-icon="" prepend-inner-icon="mdi-camera" />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">
          {{ editingId ? t('staff.save_changes') : t('staff.add_button') }}
        </v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
