<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const { items: staffList, loading, fetchAll, destroy } = useApiCrud('/staff', { entityName: t('staff.entity') })
const { search, filtered } = useTableSearch(staffList, ['full_name', 'position', 'phone', 'email'])
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const photoFile = ref(null)
const emptyForm = () => ({ full_name: '', email: '', phone: '', position: '', hire_date: '', status: 'active' })
const form = reactive(emptyForm())

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
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || t('staff.save_failed'))
  }
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

onMounted(fetchAll)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader :title="t('staff.title')" :subtitle="t('staff.subtitle')" :buttonText="t('staff.new')" @action="openCreate" />

      <div class="w-full sm:max-w-xs">
        <SearchInput v-model="search" :placeholder="t('staff.search_placeholder')" />
      </div>

      <div class="table-wrap">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ t('common.name') }}</th>
                <th>{{ t('staff.position') }}</th>
                <th>{{ t('common.phone') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in filtered" :key="s.id">
                <td>
                  <div class="flex items-center gap-3">
                    <img v-if="s.photo_path_url" :src="s.photo_path_url" class="w-8 h-8 rounded-full object-cover flex-shrink-0" alt="" />
                    <span v-else class="w-8 h-8 rounded-full bg-surface-3 border border-line flex-shrink-0"></span>
                    <span class="font-medium text-fg">{{ s.full_name }}</span>
                  </div>
                </td>
                <td>{{ s.position || '—' }}</td>
                <td>{{ s.phone || '—' }}</td>
                <td>
                  <span class="badge" :class="s.status === 'active' ? 'badge-success' : 'badge-neutral'">{{ t(`status.${s.status}`) }}</span>
                </td>
                <td class="text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="openEdit(s)" title="Edit" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z" /></svg>
                    </button>
                    <button @click="deletingId = s.id" title="Delete" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td colspan="5" class="py-10 text-center text-faint">{{ search ? t('staff.empty_search') : t('staff.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('staff.edit_title') : t('staff.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
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
        <button type="submit" class="btn-primary w-full">
          {{ editingId ? t('staff.save_changes') : t('staff.add_button') }}
        </button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
