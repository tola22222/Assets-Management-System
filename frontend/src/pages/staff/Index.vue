<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useBulkSelect } from '../../composables/useBulkSelect'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const auth = useAuthStore()
// Staff records are OPM-only to write. The restriction is enforced by
// abort_unless() inside Api\StaffController (not by role: middleware in
// api.php), so it is easy to miss when reading the route file alone.
const isOpm = computed(() => auth.user?.role === 'operations_hr_manager')
const { items: staffList, loading, fetchAll, destroy, destroyMany } = useApiCrud('/staff', { entityName: t('staff.entity') })
const { search, filtered: searched } = useTableSearch(staffList, ['full_name', 'position', 'phone', 'email'])
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(searched, { defaultKey: 'full_name' })
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(filtered)
const confirmingBulkDelete = ref(false)
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const photoFile = ref(null)
const locations = ref([])
const emptyForm = () => ({ full_name: '', email: '', phone: '', position: '', hire_date: '', status: 'active', location_id: '' })
const form = reactive(emptyForm())

async function loadLocations() {
  try {
    const { data } = await http.get('/locations')
    locations.value = data
  } catch {
    locations.value = []
  }
}

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
    location_id: staff.location_id || '',
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
    toast.error(errorMessage(e, t('staff.save_failed')))
  }
}

async function confirmDelete() {
  try {
    await destroy(deletingId.value)
  } catch {
    // useApiCrud already showed why; just clean up here.
  } finally {
    deletingId.value = null
  }
}

async function confirmBulkDelete() {
  confirmingBulkDelete.value = false
  await destroyMany(selectedIds.value)
  clearSelection()
}

onMounted(() => {
  fetchAll()
  loadLocations()
})

// Pagination is the last step, applied to the finished list, so search
// and sort still consider every row rather than just the page on screen.
const { page, rowsPerPage, total, paged } = usePagination(filtered)
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('staff.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('staff.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button v-if="isOpm && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
              {{ t('common.delete_selected', { count: selectedIds.length }) }}
            </button>
            <button v-if="isOpm" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('staff.new') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('staff.search_placeholder')" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="isOpm" class="w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('full_name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'full_name'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('position')">{{ t('staff.position') }}<TableSortIcon :active="sortKey === 'position'" :direction="sortDir" /></th>
                <th>{{ t('common.phone') }}</th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th v-if="isOpm" class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in paged" :key="s.id">
                <td v-if="isOpm">
                  <input type="checkbox" :checked="selectedIds.includes(s.id)" @change="toggleSelect(s.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
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
                <td v-if="isOpm" class="text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="openEdit(s)" :title="t('common.edit')" class="btn-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <button @click="deletingId = s.id" :title="t('common.delete')" class="btn-icon-danger">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td :colspan="isOpm ? 6 : 4" class="py-10 text-center text-faint">{{ search ? t('staff.empty_search') : t('staff.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
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
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('staff.site') }}</label>
            <select v-model="form.location_id" class="input">
              <option value="">{{ t('common.select_location') }}</option>
              <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
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
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="t('staff.bulk_delete_title', selectedIds.length)"
      :message="t('confirm.cannot_be_undone')"
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
  </AppLayout>
</template>
