<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
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
  items: staffList, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/staff', { filterKeys: ['status'] })
const { destroy } = useApiCrud('/staff', { entityName: t('staff.entity'), refetch: fetchPage })
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
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

function handleFileChange(e) {
  photoFile.value = e.target.files[0] || null
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

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

onMounted(fetchPage)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader :title="t('staff.title')" :subtitle="t('staff.subtitle')" :buttonText="t('staff.new')" @action="openCreate" />

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
        @delete="(row) => (deletingId = row.id)"
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
            <img v-if="item.photo_path_url" :src="item.photo_path_url" class="w-8 h-8 rounded-full object-cover flex-shrink-0" alt="" />
            <span v-else class="w-8 h-8 rounded-full bg-surface-3 border border-line flex-shrink-0" />
            <span class="font-medium text-fg">{{ item.full_name }}</span>
          </div>
        </template>
        <template #item.position="{ item }">{{ item.position || '—' }}</template>
        <template #item.phone="{ item }">{{ item.phone || '—' }}</template>
        <template #item.status="{ item }">
          <span class="badge" :class="item.status === 'active' ? 'badge-success' : 'badge-neutral'">{{ t(`status.${item.status}`) }}</span>
        </template>
      </AppDataTable>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('staff.edit_title') : t('staff.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('staff.full_name') }}</label>
            <input v-model="form.full_name" required class="input" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.email') }}</label>
              <input v-model="form.email" type="email" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.phone') }}</label>
              <input v-model="form.phone" class="input" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('staff.position') }}</label>
              <input v-model="form.position" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('staff.hire_date') }}</label>
              <input v-model="form.hire_date" type="date" class="input" />
            </div>
          </div>
          <div v-if="editingId" class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('staff.status_required') }}</label>
            <select v-model="form.status" class="input">
              <option value="active">{{ t('staff.status_active') }}</option>
              <option value="inactive">{{ t('staff.status_inactive') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('staff.photo') }}</label>
            <input type="file" accept="image/jpeg,image/png" @change="handleFileChange" class="w-full text-sm" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('staff.save_changes') : t('staff.add_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
