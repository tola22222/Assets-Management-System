<script setup>
import { ref, onMounted, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const { items: users, loading, fetchAll, create, update, destroy } = useApiCrud('/users', { entityName: t('users.entity') })
const { search, filtered: searched } = useTableSearch(users, ['name', 'email', 'role'])
const { filters, filtered: matched, hasActiveFilters, clearFilters } = useTableFilter(searched, {
  role: (row, v) => row.role === v,
  is_locked: (row, v) => String(!!row.is_locked) === v,
})
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(matched, { defaultKey: 'name' })
const toast = useToastStore()

const staffList = ref([])
const showModal = ref(null)
const editingId = ref(null)
const deletingId = ref(null)
const resettingId = ref(null)
const newPassword = ref('')
const newPasswordConfirm = ref('')

const roleLabels = computed(() => ({
  operations_hr_manager: t('users.role_admin'), staff: t('users.role_staff'),
  executive_director: t('users.role_executive_director'), finance_manager: t('users.role_finance_manager'),
}))
const roleColors = {
  operations_hr_manager: 'bg-purple-100 text-purple-700',
  executive_director: 'bg-amber-100 text-amber-700',
  finance_manager: 'bg-teal-100 text-teal-700',
  staff: 'bg-blue-100 text-blue-700',
}

const emptyForm = () => ({ name: '', email: '', password: '', role: 'staff', staff_id: '' })
const form = reactive(emptyForm())

async function loadStaff() {
  const { data } = await http.get('/staff')
  staffList.value = data
}

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(user) {
  editingId.value = user.id
  Object.assign(form, { name: user.name, email: user.email, password: '', role: user.role, staff_id: user.staff_id || '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) {
      const { password, ...rest } = form
      await update(editingId.value, rest)
    } else {
      await create(form)
    }
    showModal.value = false
  } catch (e) {
    toast.error(e.response?.data?.message || t('users.save_failed'))
  }
}

async function toggleLock(user) {
  await http.post(`/users/${user.id}/lock`)
  toast.success(user.is_locked ? t('users.unlocked') : t('users.locked_msg'))
  await fetchAll()
}

async function submitPasswordReset() {
  try {
    await http.post(`/users/${resettingId.value}/reset-password`, {
      password: newPassword.value,
      password_confirmation: newPasswordConfirm.value,
    })
    toast.success(t('users.password_reset'))
    resettingId.value = null
  } catch (e) {
    toast.error(e.response?.data?.message || t('users.reset_failed'))
  }
}

async function confirmDelete() {
  try {
    await destroy(deletingId.value)
  } catch (e) {
    toast.error(e.response?.data?.message || t('users.delete_failed'))
  } finally {
    deletingId.value = null
  }
}

onMounted(() => {
  fetchAll()
  loadStaff()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader :title="t('users.title')" :subtitle="t('users.subtitle')" :buttonText="t('users.new')" @action="openCreate" />

      <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-wrap">
        <div class="w-full sm:max-w-xs">
          <SearchInput v-model="search" :placeholder="t('users.search_placeholder')" />
        </div>
        <select v-model="filters.role" class="filter-select">
          <option value="">{{ t('users.role') }}: {{ t('common.all') }}</option>
          <option value="operations_hr_manager">{{ t('users.role_admin') }}</option>
          <option value="executive_director">{{ t('users.role_executive_director') }}</option>
          <option value="finance_manager">{{ t('users.role_finance_manager') }}</option>
          <option value="staff">{{ t('users.role_staff') }}</option>
        </select>
        <select v-model="filters.is_locked" class="filter-select">
          <option value="">{{ t('common.status') }}: {{ t('common.all') }}</option>
          <option value="false">{{ t('status.active') }}</option>
          <option value="true">{{ t('status.locked') }}</option>
        </select>
        <button v-if="hasActiveFilters" @click="clearFilters" class="btn-subtle btn-sm">{{ t('common.clear_filters') }}</button>
      </div>

      <div class="table-wrap">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="th-sort" @click="toggleSort('name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('email')">{{ t('common.email') }}<TableSortIcon :active="sortKey === 'email'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('role')">{{ t('users.role') }}<TableSortIcon :active="sortKey === 'role'" :direction="sortDir" /></th>
                <th>{{ t('common.status') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in filtered" :key="u.id">
                <td class="font-medium text-fg">{{ u.name }}</td>
                <td>{{ u.email }}</td>
                <td><span class="px-2.5 py-1 rounded-lg text-xs font-semibold" :class="roleColors[u.role]">{{ roleLabels[u.role] || u.role }}</span></td>
                <td>
                  <span class="badge" :class="u.is_locked ? 'badge-danger' : 'badge-success'">
                    {{ u.is_locked ? t('status.locked') : t('status.active') }}
                  </span>
                </td>
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="openEdit(u)" title="Edit" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z" /></svg>
                    </button>
                    <button @click="toggleLock(u)" :title="u.is_locked ? t('common.unlock') : t('common.lock')" class="w-7 h-7 rounded-lg bg-slate-500 text-white flex items-center justify-center hover:bg-slate-600 transition">
                      <svg v-if="u.is_locked" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                    </button>
                    <button @click="resettingId = u.id; newPassword = ''; newPasswordConfirm = ''" :title="t('common.reset_password')" class="w-7 h-7 rounded-lg bg-indigo-500 text-white flex items-center justify-center hover:bg-indigo-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                    </button>
                    <button @click="deletingId = u.id" title="Delete" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td colspan="5" class="py-10 text-center text-faint">{{ search ? t('users.empty_search') : t('users.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('users.edit_title') : t('users.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.email_required') }}</label>
            <input v-model="form.email" type="email" required class="input" />
          </div>
          <div v-if="!editingId" class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.password') }}</label>
            <input v-model="form.password" type="password" minlength="8" required class="input" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.role_required') }}</label>
              <select v-model="form.role" class="input">
                <option value="staff">{{ t('users.role_staff') }}</option>
                <option value="operations_hr_manager">{{ t('users.role_admin') }}</option>
                <option value="executive_director">{{ t('users.role_executive_director') }}</option>
                <option value="finance_manager">{{ t('users.role_finance_manager') }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.link_to_staff') }}</label>
              <select v-model="form.staff_id" class="input">
                <option value="">{{ t('users.none') }}</option>
                <option v-for="s in staffList" :key="s.id" :value="s.id">{{ s.full_name }}</option>
              </select>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('users.save_changes') : t('users.create_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <Modal v-if="resettingId" :title="t('users.reset_password_title')" @close="resettingId = null">
      <form @submit.prevent="submitPasswordReset">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.new_password') }}</label>
            <input v-model="newPassword" type="password" minlength="8" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.confirm_password') }}</label>
            <input v-model="newPasswordConfirm" type="password" minlength="8" required class="input" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">{{ t('users.reset_password_title') }}</button>
          <button type="button" class="btn-ghost" @click="resettingId = null">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
