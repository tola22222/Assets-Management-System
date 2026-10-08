<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import DetailModal from '../../components/ui/DetailModal.vue'
import { onRowClick } from '../../utils/rowClick'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import LocationFilter from '../../components/ui/LocationFilter.vue'
import SearchSelect from '../../components/ui/SearchSelect.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'
import { useTableSort } from '../../composables/useTableSort'
import { useBulkSelect } from '../../composables/useBulkSelect'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import { usePermissions } from '../../composables/usePermissions'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'
import ImageField from '../../components/ui/ImageField.vue'

const { t } = useI18n()
const auth = useAuthStore()
// Staff records are OPM-only to write. The restriction is enforced by
// abort_unless() inside Api\StaffController (not by role: middleware in
// api.php), so it is easy to miss when reading the route file alone.
// HR or the Accountant (same access; see User::isAdministrator on the server).
// HR or the Accountant — or a custom role granting the ability (the same
// rule as the server's role: guard; see usePermissions().allows).
const { allows } = usePermissions()
const ADMIN_ROLES = ['operations_hr_manager', 'finance_manager']
const canCreate = computed(() => allows(ADMIN_ROLES, 'staff', 'create'))
const canUpdate = computed(() => allows(ADMIN_ROLES, 'staff', 'update'))
const canDelete = computed(() => allows(ADMIN_ROLES, 'staff', 'delete'))
// The selection and actions columns show when a row can be changed at all.
const canManage = computed(() => canUpdate.value || canDelete.value)
const { items: staffList, loading, fetchAll, destroy, destroyMany } = useApiCrud('/staff', { entityName: t('staff.entity') })
const { search, filtered: searched } = useTableSearch(staffList, ['full_name', 'position', 'phone', 'email'])
// Location filter (the drop-down beside search): anyone whose program covers
// that school (or, before they have a program, whose old site it is).
const { filters, filtered: filteredStaff } = useTableFilter(searched, {
  location: (s, v) => (s.program?.locations?.length
    ? s.program.locations.some((l) => String(l.id) === String(v))
    : String(s.location_id) === v),
})
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(filteredStaff, { defaultKey: 'created_at', defaultDir: 'desc' })
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(filtered)
const confirmingBulkDelete = ref(false)
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const photoFile = ref(null)
// The staff photo already on the record; shown while editing so it is clear a
// person already has one.
const existingPhoto = ref(null)
// Program → Location → Staff: the staff member works at one or more
// locations, all in ONE program, and their program comes from them —
// read-only, never picked. The server applies the same rule.
const locationOptions = ref([])
const emptyForm = () => ({ location_ids: [], full_name: '', email: '', phone: '', position: '', hire_date: '', status: 'active' })
const form = reactive(emptyForm())
// The program of the staff member being edited: a location saved before the
// one-program rule may still list several, and then their own one is kept.
const editingProgramId = ref(null)

const programIdsOf = (l) => (l?.programs || []).map((p) => p.id)
const chosenLocations = computed(() => locationOptions.value.filter((l) => form.location_ids.some((id) => String(id) === String(l.id))))
// The programs every chosen location shares.
const sharedProgramIds = computed(() => {
  if (!chosenLocations.value.length) return []
  return chosenLocations.value.map(programIdsOf).reduce((acc, ids) => acc.filter((id) => ids.includes(id)))
})
const derivedProgram = computed(() => {
  const shared = sharedProgramIds.value
  const id = shared.length === 1 ? shared[0] : (shared.includes(editingProgramId.value) ? editingProgramId.value : null)
  if (id === null) return null
  for (const l of chosenLocations.value) {
    const p = (l.programs || []).find((x) => x.id === id)
    if (p) return p
  }
  return null
})
// Only locations of that same program can be added (and only ones that have
// a program at all); the ones already chosen always stay listed.
const staffLocationOptions = computed(() => {
  const shared = sharedProgramIds.value
  return locationOptions.value
    .filter((l) => form.location_ids.some((id) => String(id) === String(l.id))
      || (programIdsOf(l).length && (!chosenLocations.value.length || programIdsOf(l).some((id) => shared.includes(id)))))
    .map((l) => ({ value: l.id, label: l.name, sub: l.code || undefined }))
})

async function loadLocationOptions() {
  try {
    const { data } = await http.get('/locations')
    locationOptions.value = data
  } catch {
    locationOptions.value = []
  }
}

function openCreate() {
  editingId.value = null
  editingProgramId.value = null
  Object.assign(form, emptyForm())
  photoFile.value = null
  existingPhoto.value = null
  showModal.value = true
}

function openEdit(staff) {
  editingId.value = staff.id
  editingProgramId.value = staff.program_id ?? null
  Object.assign(form, {
    location_ids: staff.locations?.length ? staff.locations.map((l) => l.id) : [staff.location_id].filter(Boolean),
    full_name: staff.full_name, email: staff.email || '', phone: staff.phone || '',
    position: staff.position || '', hire_date: staff.hire_date || '', status: staff.status || 'active',
  })
  photoFile.value = null
  existingPhoto.value = staff.photo_path_url || null
  showModal.value = true
}

async function handleSubmit() {
  const fd = new FormData()
  Object.entries(form).forEach(([k, v]) => {
    if (Array.isArray(v)) v.forEach((item) => fd.append(`${k}[]`, item))
    else if (v !== '') fd.append(k, v)
  })
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

// View: clicking a row opens its details (read-only), in the shared dialog.
const viewing = ref(null)
const viewRows = computed(() => {
  const r = viewing.value
  if (!r) return []
  return [
    { label: t('staff.photo'), value: r.photo_path_url, type: 'image' },
    { label: t('common.name'), value: r.full_name },
    { label: t('staff.position'), value: r.position },
    { label: t('common.phone'), value: r.phone },
    { label: t('common.email'), value: r.email },
    { label: t('common.location'), value: (r.locations || []).map((l) => l.name).join(', ') || r.location?.name },
    { label: t('staff.program'), value: r.program?.name },
    { label: t('staff.hire_date'), value: (r.hire_date || '').slice(0, 10) },
    { label: t('common.status'), value: r.status, type: 'status' },
  ]
})

onMounted(() => {
  fetchAll()
  loadLocationOptions()
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
            <button v-if="canDelete && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
              {{ t('common.delete_selected', { count: selectedIds.length }) }}
            </button>
            <button v-if="canCreate" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('staff.new') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('staff.search_placeholder')" />
          </div>
          <LocationFilter v-model="filters.location" />
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="canManage" class="w-10">
                  <input v-if="canDelete" type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('full_name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'full_name'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('position')">{{ t('staff.position') }}<TableSortIcon :active="sortKey === 'position'" :direction="sortDir" /></th>
                <th>{{ t('common.phone') }}</th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th v-if="canManage" class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in paged" :key="s.id" class="cursor-pointer" @click="onRowClick($event, () => viewing = s)">
                <td v-if="canManage">
                  <input v-if="canDelete" type="checkbox" :checked="selectedIds.includes(s.id)" @change="toggleSelect(s.id)" class="rounded border-line text-brand focus:ring-brand/30" />
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
                <!-- Status, plus "No login" when no user account is linked: the
                     person can't sign in, so they can't accept a transfer. -->
                <td>
                  <div class="flex flex-nowrap items-center gap-1.5 whitespace-nowrap">
                    <span class="badge" :class="s.status === 'active' ? 'badge-success' : 'badge-neutral'">{{ t(`status.${s.status}`) }}</span>
                    <span v-if="s.has_login === false" class="badge-warning" :title="t('staff.no_login_hint')">{{ t('programs.no_login') }}</span>
                  </div>
                </td>
                <td v-if="canManage" class="text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button v-if="canUpdate" @click="openEdit(s)" :title="t('common.edit')" class="btn-icon-edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <button v-if="canDelete" @click="deletingId = s.id" :title="t('common.delete')" class="btn-icon-danger">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td :colspan="canManage ? 6 : 4" class="py-10 text-center text-faint">{{ search ? t('staff.empty_search') : t('staff.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('staff.edit_title') : t('staff.create_title')" @close="showModal = false">
      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="modal-body space-y-4">
          <!-- Locations first: one or more, all in the same program. -->
          <div class="form-group">
            <label class="label">{{ t('staff.locations') }} <span class="text-red-500">*</span></label>
            <SearchSelect v-model="form.location_ids" multiple required input-class="input" :placeholder="t('staff.select_locations')"
              :options="staffLocationOptions" />
            <p class="text-xs text-muted mt-1">{{ t('staff.locations_hint') }}</p>
          </div>
          <!-- Loaded from the locations, never picked. -->
          <div class="form-group">
            <label class="label">{{ t('staff.program') }}</label>
            <input :value="derivedProgram?.name || ''" readonly class="input bg-surface-2"
              :placeholder="form.location_ids.length ? '' : t('staff.choose_location_first')" />
            <p v-if="form.location_ids.length && !derivedProgram" class="text-xs text-danger mt-1">{{ t('staff.location_multi_program') }}</p>
            <p v-else class="text-xs text-muted mt-1">{{ t('staff.program_auto_hint') }}</p>
          </div>
          <div class="form-group">
            <label class="label">{{ t('staff.full_name') }}</label>
            <input v-model="form.full_name" required class="input" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
              <label class="label">{{ t('common.email') }}</label>
              <input v-model="form.email" type="email" class="input" />
            </div>
            <div class="form-group">
              <label class="label">{{ t('common.phone') }}</label>
              <input v-model="form.phone" class="input" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
              <label class="label">{{ t('staff.position') }}</label>
              <input v-model="form.position" class="input" />
            </div>
            <div class="form-group">
              <label class="label">{{ t('staff.hire_date') }}</label>
              <input v-model="form.hire_date" type="date" class="input" />
            </div>
          </div>
          <div v-if="editingId" class="form-group">
            <label class="label">{{ t('staff.status_required') }}</label>
            <select v-model="form.status" class="input">
              <option value="active">{{ t('staff.status_active') }}</option>
              <option value="inactive">{{ t('staff.status_inactive') }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">{{ t('staff.photo') }}</label>
            <ImageField v-model="photoFile" :existing="existingPhoto" :aspect="1" :hint="t('image.field_hint')" />
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('staff.save_changes') : t('staff.add_button') }}
          </button>
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
    <DetailModal v-if="viewing" :title="t('common.details')" :rows="viewRows" @close="viewing = null" />
  </AppLayout>
</template>
