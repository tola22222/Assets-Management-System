<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useBulkSelect } from '../../composables/useBulkSelect'
import { useAuthStore } from '../../stores/auth'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const auth = useAuthStore()
const canManage = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))
const { items: suppliers, loading, fetchAll, create, update, destroy, destroyMany } = useApiCrud('/suppliers', { entityName: t('suppliers.entity') })
const { search, filtered: searched } = useTableSearch(suppliers, ['name', 'phone', 'address'])
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(searched, { defaultKey: 'name' })
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(filtered)
const confirmingBulkDelete = ref(false)

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', phone: '', address: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', phone: '', address: '' })
  showModal.value = true
}

function openEdit(supplier) {
  editingId.value = supplier.id
  Object.assign(form, { name: supplier.name, phone: supplier.phone || '', address: supplier.address || '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) await update(editingId.value, form)
    else await create(form)
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
            <button v-if="canManage && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
              {{ t('common.delete_selected', { count: selectedIds.length }) }}
            </button>
            <button v-if="canManage" @click="openCreate" class="btn-primary btn-sm">
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
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th>{{ t('common.phone') }}</th>
                <th>{{ t('common.address') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in paged" :key="s.id">
                <td v-if="canManage">
                  <input type="checkbox" :checked="selectedIds.includes(s.id)" @change="toggleSelect(s.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="font-medium text-fg">{{ s.name }}</td>
                <td>{{ s.phone || '—' }}</td>
                <td>{{ s.address || '—' }}</td>
                <td class="text-right">
                  <div v-if="canManage" class="flex items-center justify-end gap-1.5">
                    <button @click="openEdit(s)" :title="t('common.edit')" class="btn-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <button @click="deletingId = s.id" :title="t('common.delete')" class="btn-icon-danger">
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
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('suppliers.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.phone') }}</label>
            <input v-model="form.phone" class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.address') }}</label>
            <textarea v-model="form.address" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('suppliers.save_changes') : t('suppliers.create_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
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
  </AppLayout>
</template>
