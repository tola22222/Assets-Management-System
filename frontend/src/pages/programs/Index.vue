<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
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
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const auth = useAuthStore()
// HR or the Accountant (same access; see User::isAdministrator on the server).
const isOpm = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))
const { items: programs, loading, fetchAll, create, update, destroy, destroyMany } = useApiCrud('/programs', { entityName: t('programs.entity') })
const { search, filtered: searched } = useTableSearch(programs, ['name', 'description', (p) => schoolNames(p)])
// Location filter (the drop-down beside search): any of the schools a program
// runs at, applied after search and before sort.
const { filters, filtered: filteredPrograms } = useTableFilter(searched, {
  location: (p, v) => programSchoolIds(p).includes(String(v)),
})
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(filteredPrograms, {
  defaultKey: 'created_at', defaultDir: 'desc',
  paths: { school: 'location.name', lead: 'responsible_staff.full_name' },
})
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(filtered)
const confirmingBulkDelete = ref(false)
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', description: '', location_ids: [], responsible_staff_id: '' })

// A program runs at one or more schools and has one Responsible Staff, who
// accepts deliveries at every one of them. Both are required.
const locations = ref([])
const staff = ref([])

// The schools a program is linked to (older rows: just its location_id).
function programSchoolIds(p) {
  const ids = p.locations?.length ? p.locations.map((l) => l.id) : [p.location_id].filter(Boolean)
  return ids.map(String)
}
function schoolNames(p) {
  return p.locations?.length ? p.locations.map((l) => l.name).join(', ') : (p.location?.name || '')
}

// A staff member leads at most one program, so anyone already claimed is left
// out of the picker entirely — except the lead of the program being edited,
// who would otherwise vanish from their own form.
const takenStaffIds = computed(
  () =>
    new Set(
      programs.value
        .filter((p) => p.responsible_staff_id && p.id !== editingId.value)
        .map((p) => String(p.responsible_staff_id)),
    ),
)

// A staff member belongs to ONE program, so the lead must be someone not in a
// program yet (saving puts them in this one) or already in this program.
const leadCandidates = computed(() =>
  staff.value.filter(
    (s) => (!s.program_id || s.program_id === editingId.value) && !takenStaffIds.value.has(String(s.id)),
  ),
)

async function loadOptions() {
  try {
    const [l, s] = await Promise.all([http.get('/locations'), http.get('/staff')])
    locations.value = l.data
    staff.value = s.data
  } catch (e) {
    toast.error(errorMessage(e, t('programs.options_failed')))
  }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', description: '', location_ids: [], responsible_staff_id: '' })
  showModal.value = true
}

function openEdit(program) {
  editingId.value = program.id
  Object.assign(form, {
    name: program.name,
    description: program.description || '',
    location_ids: programSchoolIds(program).map(Number),
    responsible_staff_id: program.responsible_staff_id || '',
  })
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) await update(editingId.value, form)
    else await create(form)
    showModal.value = false
    // Saving can put the lead into this program — refresh the staff picker.
    loadOptions()
  } catch {
    // useApiCrud already showed why; just clean up here.
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
  try {
    await destroyMany(selectedIds.value)
  } catch {
    // useApiCrud already showed why; just clean up here.
  } finally {
    clearSelection()
  }
}

onMounted(() => {
  fetchAll()
  loadOptions()
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
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('programs.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('programs.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button v-if="isOpm && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
              {{ t('common.delete_selected', { count: selectedIds.length }) }}
            </button>
            <button v-if="isOpm" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('programs.new') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('programs.search_placeholder')" />
          </div>
          <LocationFilter v-model="filters.location" />
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="isOpm" class="w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('school')">{{ t('programs.school') }}<TableSortIcon :active="sortKey === 'school'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('lead')">{{ t('programs.responsible_staff') }}<TableSortIcon :active="sortKey === 'lead'" :direction="sortDir" /></th>
                <th>{{ t('common.status') }}</th>
                <th>{{ t('common.description') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in paged" :key="p.id">
                <td v-if="isOpm">
                  <input type="checkbox" :checked="selectedIds.includes(p.id)" @change="toggleSelect(p.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="font-medium text-fg">{{ p.name }}</td>
                <td>{{ schoolNames(p) || '—' }}</td>
                <td>{{ p.responsible_staff?.full_name || '—' }}</td>
                <!-- Status: whether this program's lead can actually accept a
                     transfer for the school. No lead and a lead with no login
                     account both leave the school unable to receive anything —
                     the second is the sneakier one, since a name is filled in
                     but nobody can act on it. -->
                <td>
                  <span v-if="!p.responsible_staff" class="badge-warning">{{ t('programs.no_lead') }}</span>
                  <span v-else-if="!p.responsible_staff_has_login" class="badge-warning" :title="t('programs.no_login_hint')">{{ t('programs.no_login') }}</span>
                  <span v-else class="badge-success">{{ t('status.active') }}</span>
                </td>
                <td>{{ p.description || '—' }}</td>
                <td class="text-right">
                  <div v-if="isOpm" class="flex items-center justify-end gap-1.5">
                    <button @click="openEdit(p)" :title="t('common.edit')" class="btn-icon-edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <button @click="deletingId = p.id" :title="t('common.delete')" class="btn-icon-danger">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                  <span v-else class="text-faint text-xs">—</span>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td :colspan="isOpm ? 7 : 6" class="py-10 text-center text-faint">{{ search ? t('programs.empty_search') : t('programs.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('programs.edit_title') : t('programs.create_title')" @close="showModal = false">
      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="modal-body space-y-4">
          <div class="form-group">
            <label class="label">{{ t('programs.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <!-- Schools: a program can run at several. Its lead accepts deliveries
               at every one, and its staff can see and manage all of them. -->
          <div class="form-group">
            <div class="flex items-center justify-between mb-1.5">
              <label class="label !mb-0">{{ t('programs.schools_required') }}</label>
              <span class="text-xs text-muted">{{ t('asset_transfers.ticked_count', { n: form.location_ids.length, total: locations.length }) }}</span>
            </div>
            <SearchSelect v-model="form.location_ids" multiple required input-class="input" :placeholder="t('programs.select_schools')"
              :options="locations.map((l) => ({ value: l.id, label: l.name }))" />
          </div>
          <div class="form-group">
            <label class="label">{{ t('programs.responsible_staff_required') }}</label>
            <SearchSelect v-model="form.responsible_staff_id" required input-class="input" :placeholder="t('programs.select_staff')"
              :options="leadCandidates.map((s) => ({ value: s.id, label: s.full_name }))" />
            <p v-if="!leadCandidates.length" class="text-xs text-danger mt-1">{{ t('programs.all_staff_taken') }}</p>
            <p v-else class="text-xs text-muted mt-1">{{ t('programs.responsible_staff_hint') }}</p>
          </div>
          <div class="form-group">
            <label class="label">{{ t('common.description') }}</label>
            <textarea v-model="form.description" rows="2" class="textarea"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('programs.save_changes') : t('programs.create_button') }}
          </button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="t('programs.bulk_delete_title', selectedIds.length)"
      :message="t('confirm.cannot_be_undone')"
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
  </AppLayout>
</template>
