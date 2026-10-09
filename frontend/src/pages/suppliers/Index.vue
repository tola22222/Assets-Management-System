<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCreateDraft } from '../../composables/useCreateDraft'
import AppLayout from '../../layouts/AppLayout.vue'
import DetailModal from '../../components/ui/DetailModal.vue'
import { onRowClick } from '../../utils/rowClick'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useBulkSelect } from '../../composables/useBulkSelect'
import { useAuthStore } from '../../stores/auth'
import { usePermissions } from '../../composables/usePermissions'
import TablePagination from '../../components/ui/TablePagination.vue'
import ImageField from '../../components/ui/ImageField.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const auth = useAuthStore()
// HR or the Accountant — or a custom role granting the ability (the same
// rule as the server's role: guard; see usePermissions().allows).
const { allows } = usePermissions()
const ADMIN_ROLES = ['operations_hr_manager', 'finance_manager']
const canCreate = computed(() => allows(ADMIN_ROLES, 'suppliers', 'create'))
const canUpdate = computed(() => allows(ADMIN_ROLES, 'suppliers', 'update'))
const canDelete = computed(() => allows(ADMIN_ROLES, 'suppliers', 'delete'))
// The selection and actions columns show when a row can be changed at all.
const canManage = computed(() => canUpdate.value || canDelete.value)
const { items: suppliers, loading, fetchAll, create, update, destroy, destroyMany } = useApiCrud('/suppliers', { entityName: t('suppliers.entity') })
const { search, filtered: searched } = useTableSearch(suppliers, ['name', 'phone', 'address'])
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(searched, { defaultKey: 'created_at', defaultDir: 'desc' })
// The header tick box selects the rows on the page being shown, not every
// match ("paged" is set up with the pagination, further down).
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(computed(() => paged.value))
const confirmingBulkDelete = ref(false)

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', phone: '', address: '' })
// A Create form closed by accident keeps what was typed (this browser tab
// only, never sent anywhere); cleared once the record is really created.
const draft = useCreateDraft('suppliers', form, () => showModal.value && !editingId.value)
// Photo or logo: the same photo box as the other forms (square, like the
// staff photo and the organisation logo). Kept unless a new one is chosen.
const imageFile = ref(null)
const existingImage = ref(null)

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', phone: '', address: '' })
  imageFile.value = null
  existingImage.value = null
  draft.restore()
  showModal.value = true
}

function openEdit(supplier) {
  editingId.value = supplier.id
  Object.assign(form, { name: supplier.name, phone: supplier.phone || '', address: supplier.address || '' })
  imageFile.value = null
  existingImage.value = supplier.image_url || null
  showModal.value = true
}

async function handleSubmit() {
  const fd = new FormData()
  fd.append('name', form.name)
  fd.append('phone', form.phone)
  fd.append('address', form.address)
  if (imageFile.value) fd.append('image', imageFile.value)
  try {
    if (editingId.value) await update(editingId.value, fd)
    else {
      await create(fd)
      draft.clear()
    }
    // Only close on success, so a rejected save keeps the entered values.
    showModal.value = false
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
  await destroyMany(selectedIds.value)
  clearSelection()
}

onMounted(fetchAll)

// View: clicking a row opens its details (read-only), in the shared dialog.
const viewing = ref(null)
const viewRows = computed(() => {
  const r = viewing.value
  if (!r) return []
  return [
    { label: t('common.name'), value: r.name },
    { label: t('common.phone'), value: r.phone },
    { label: t('common.address'), value: r.address, type: 'multiline' },
    { label: t('suppliers.photo'), value: r.image_url, type: 'image' },
  ]
})

// A photo whose file is gone from storage shows the placeholder instead of a
// broken image.
const brokenImages = ref(new Set())
const supplierImage = (s) => (s.image_url && !brokenImages.value.has(s.image_url) ? s.image_url : null)
function imageFailed(url) {
  brokenImages.value = new Set(brokenImages.value).add(url)
}

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
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('suppliers.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('suppliers.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button v-if="canDelete && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
              {{ t('common.delete_selected', { count: selectedIds.length }) }}
            </button>
            <button v-if="canCreate" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('suppliers.new') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('suppliers.search_placeholder')" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="canManage" class="w-10">
                  <input v-if="canDelete" type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th>{{ t('common.phone') }}</th>
                <th>{{ t('common.address') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in paged" :key="s.id" class="cursor-pointer" @click="onRowClick($event, () => viewing = s)">
                <td v-if="canManage">
                  <input v-if="canDelete" type="checkbox" :checked="selectedIds.includes(s.id)" @change="toggleSelect(s.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="font-medium text-fg">
                  <span class="flex items-center gap-3 min-w-0">
                    <img v-if="supplierImage(s)" :src="supplierImage(s)" :alt="s.name" loading="lazy" @error="imageFailed(s.image_url)" class="w-10 h-10 rounded-lg object-cover border border-line flex-shrink-0" />
                    <span v-else class="w-10 h-10 rounded-lg bg-surface-2 border border-line flex items-center justify-center text-faint flex-shrink-0">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><polyline points="21 15 16 10 5 21" /></svg>
                    </span>
                    <span>{{ s.name }}</span>
                  </span>
                </td>
                <td>{{ s.phone || '—' }}</td>
                <td>{{ s.address || '—' }}</td>
                <td class="text-right">
                  <div v-if="canManage" class="flex items-center justify-end gap-1.5">
                    <button v-if="canUpdate" @click="openEdit(s)" :title="t('common.edit')" class="btn-icon-edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <button v-if="canDelete" @click="deletingId = s.id" :title="t('common.delete')" class="btn-icon-danger">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                  <span v-else class="text-faint text-xs">—</span>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td :colspan="canManage ? 5 : 4" class="py-10 text-center text-faint">{{ search ? t('suppliers.empty_search') : t('suppliers.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('suppliers.edit_title') : t('suppliers.create_title')" @close="showModal = false">
      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="modal-body space-y-4">
          <div class="form-group">
            <label class="label">{{ t('suppliers.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="form-group">
            <label class="label">{{ t('common.phone') }}</label>
            <input v-model="form.phone" class="input" />
          </div>
          <div class="form-group">
            <label class="label">{{ t('common.address') }}</label>
            <textarea v-model="form.address" rows="2" class="textarea"></textarea>
          </div>
          <div class="form-group">
            <label class="label">{{ t('suppliers.photo') }}</label>
            <ImageField v-model="imageFile" :existing="existingImage" :aspect="1" :hint="t('image.field_hint')" />
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('suppliers.save_changes') : t('suppliers.create_button') }}
          </button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="t('suppliers.bulk_delete_title', selectedIds.length)"
      :message="t('confirm.cannot_be_undone')"
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
    <DetailModal v-if="viewing" :title="t('common.details')" :rows="viewRows" @close="viewing = null" />
  </AppLayout>
</template>
