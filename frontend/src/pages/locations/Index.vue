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
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const { items: locations, loading, fetchAll, create, update, destroy, destroyMany } = useApiCrud('/locations', { entityName: t('locations.entity') })
const { search, filtered: searched } = useTableSearch(locations, ['name', 'code', 'type', 'description'])
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(searched, { defaultKey: 'name', paths: { count: 'assets_count' } })
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(filtered)
const confirmingBulkDelete = ref(false)
const toast = useToastStore()
const auth = useAuthStore()
// Location writes are restricted to role:operations_hr_manager in api.php;
// without this gate every other role saw buttons that could only 403.
const isOpm = computed(() => auth.user?.role === 'operations_hr_manager')

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', code: '', type: 'office', description: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', code: '', type: 'office', description: '' })
  showModal.value = true
}

function openEdit(location) {
  editingId.value = location.id
  Object.assign(form, { name: location.name, code: location.code || '', type: location.type, description: location.description || '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) {
      await update(editingId.value, form)
    } else {
      await create(form)
    }
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
  try {
    await destroyMany(selectedIds.value)
  } catch {
    // useApiCrud already showed why; just clean up here.
  } finally {
    clearSelection()
  }
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
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('locations.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('locations.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button v-if="isOpm && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
              {{ t('common.delete_selected', { count: selectedIds.length }) }}
            </button>
            <button v-if="isOpm" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('locations.new') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('locations.search_placeholder')" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="isOpm" class="w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('code')">{{ t('locations.code') }}<TableSortIcon :active="sortKey === 'code'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('type')">{{ t('locations.type') }}<TableSortIcon :active="sortKey === 'type'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('count')">{{ t('locations.stock_records') }}<TableSortIcon :active="sortKey === 'count'" :direction="sortDir" /></th>
                <th v-if="isOpm" class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="loc in paged" :key="loc.id">
                <td v-if="isOpm">
                  <input type="checkbox" :checked="selectedIds.includes(loc.id)" @change="toggleSelect(loc.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="font-medium text-fg">{{ loc.name }}</td>
                <td>
                  <span v-if="loc.code" class="badge-neutral font-mono">{{ loc.code }}</span>
                  <span v-else class="badge-danger" :title="t('locations.code_missing_hint')">{{ t('locations.code_missing') }}</span>
                </td>
                <td class="capitalize">{{ loc.type }}</td>
                <td>{{ loc.assets_count ?? 0 }}</td>
                <td v-if="isOpm" class="text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="openEdit(loc)" :title="t('common.edit')" class="btn-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <button @click="deletingId = loc.id" :title="t('common.delete')" class="btn-icon-danger">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td :colspan="isOpm ? 6 : 4" class="py-10 text-center text-faint">{{ search ? t('locations.empty_search') : t('locations.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('locations.edit_title') : t('locations.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('locations.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('locations.code_required') }}</label>
            <input
              v-model="form.code"
              required
              maxlength="4"
              pattern="[A-Za-z0-9]{2,4}"
              class="input uppercase font-mono"
              placeholder="SR"
              @input="form.code = form.code.toUpperCase()"
            />
            <p class="text-xs text-faint">{{ t('locations.code_help') }}</p>
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
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="t('locations.bulk_delete_title', selectedIds.length)"
      :message="t('confirm.cannot_be_undone')"
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
  </AppLayout>
</template>
