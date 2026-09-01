<script setup>
import { ref, onMounted, reactive, computed } from 'vue'
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
import { usePermissions } from '../../composables/usePermissions'
import RolesPanel from './RolesPanel.vue'

const { t } = useI18n()
const auth = useAuthStore()
const { items: users, loading, fetchAll, create, update, destroy, destroyMany } = useApiCrud('/users', { entityName: t('users.entity') })
const { search, filtered: searched } = useTableSearch(users, ['name', 'email', 'role'])
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(searched, { defaultKey: 'name' })
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(filtered)
const confirmingBulkDelete = ref(false)
const toast = useToastStore()

const { can } = usePermissions()
const tab = ref('users')

// --- role assignment + effective permissions -------------------------------
const allRoles = ref([])
const selectedRoleIds = ref([])
const permissionsFor = ref(null)
const permissionsLoading = ref(false)
const modules = ref([])

async function loadRoleOptions() {
  try {
    const [r, c] = await Promise.all([http.get('/roles'), http.get('/roles/catalogue')])
    // Built-in roles mirror the users.role dropdown, so offering them here too
    // would let the same access be granted twice in two different ways.
    allRoles.value = r.data.filter((x) => !x.is_system)
    modules.value = c.data.modules
  } catch {
    allRoles.value = []
  }
}

function moduleLabel(key) {
  return modules.value.find((m) => m.key === key)?.label || key
}

async function openPermissions(user) {
  permissionsLoading.value = true
  permissionsFor.value = { user, data: null }
  try {
    const { data } = await http.get(`/users/${user.id}/permissions`)
    permissionsFor.value = { user, data }
  } catch (e) {
    permissionsFor.value = null
    toast.error(errorMessage(e, t('users.permissions_failed')))
  } finally {
    permissionsLoading.value = false
  }
}

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
  try {
    const { data } = await http.get('/staff')
    staffList.value = data
  } catch (e) {
    staffList.value = []
    toast.error(errorMessage(e, t('users.staff_load_failed')))
  }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  selectedRoleIds.value = []
  showModal.value = true
}

function openEdit(user) {
  editingId.value = user.id
  Object.assign(form, { name: user.name, email: user.email, password: '', role: user.role, staff_id: user.staff_id || '' })
  selectedRoleIds.value = (user.roles || []).map((r) => r.id)
  showModal.value = true
}

async function handleSubmit() {
  try {
    let userId = editingId.value
    if (editingId.value) {
      const { password, ...rest } = form
      await update(editingId.value, rest)
    } else {
      const created = await create(form)
      userId = created?.id
    }

    // Role assignment is a separate endpoint (it is guarded by roles.update,
    // not users.update), so it is saved after the account itself succeeds.
    if (userId && can('roles', 'update')) {
      await http.post(`/users/${userId}/roles`, { roles: selectedRoleIds.value })
      await fetchAll()
    }

    showModal.value = false
  } catch (e) {
    // useApiCrud reports its own failures; this only covers the roles call.
    if (e?.config?.url?.includes('/roles')) {
      toast.error(errorMessage(e, t('users.roles_save_failed')))
    }
  }
}

function toggleRole(id) {
  const i = selectedRoleIds.value.indexOf(id)
  if (i === -1) selectedRoleIds.value.push(id)
  else selectedRoleIds.value.splice(i, 1)
}

async function toggleLock(user) {
  try {
    await http.post(`/users/${user.id}/lock`)
    toast.success(user.is_locked ? t('users.unlocked') : t('users.locked_msg'))
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('users.lock_failed')))
  }
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
    toast.error(errorMessage(e, t('users.reset_failed')))
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
  loadStaff()
  loadRoleOptions()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('users.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('users.subtitle') }}</p>
          </div>
          <div v-if="tab === 'users'" class="flex items-center gap-2 flex-shrink-0">
            <button v-if="selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
              {{ t('users.delete_selected') }} ({{ selectedIds.length }})
            </button>
            <button @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('users.new') }}
            </button>
          </div>
        </div>

        <!-- Accounts and the roles they can hold are two views of the same
             thing, so they live side by side rather than on separate pages. -->
        <div v-if="can('roles', 'view')" class="flex items-center gap-1 bg-surface-2 rounded-xl p-1 w-fit mb-6">
          <button
            v-for="pane in [{ key: 'users', label: t('users.tab_users') }, { key: 'roles', label: t('users.tab_roles') }]"
            :key="pane.key"
            @click="tab = pane.key"
            class="px-4 py-1.5 rounded-lg text-xs font-semibold transition"
            :class="tab === pane.key ? 'bg-brand text-white' : 'text-muted hover:text-fg'"
          >{{ pane.label }}</button>
        </div>

        <RolesPanel v-if="tab === 'roles'" />

        <template v-else>
        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('users.search_placeholder')" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('common.name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('email')">{{ t('common.email') }}<TableSortIcon :active="sortKey === 'email'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('role')">{{ t('users.role') }}<TableSortIcon :active="sortKey === 'role'" :direction="sortDir" /></th>
                <th>{{ t('common.status') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in filtered" :key="u.id">
                <td>
                  <input type="checkbox" :checked="selectedIds.includes(u.id)" @change="toggleSelect(u.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="font-medium text-fg">{{ u.name }}</td>
                <td>{{ u.email }}</td>
                <td>
                  <span class="px-2.5 py-1 rounded-lg text-xs font-semibold" :class="roleColors[u.role]">{{ roleLabels[u.role] || u.role }}</span>
                  <span v-for="r in (u.roles || [])" :key="r.id"
                    class="badge ml-1" :class="r.is_active ? 'badge-info' : 'badge-neutral'"
                    :title="r.is_active ? '' : t('roles.inactive_grants_nothing')">{{ r.name }}</span>
                </td>
                <td>
                  <span class="badge" :class="u.is_locked ? 'badge-danger' : 'badge-success'">
                    {{ u.is_locked ? t('status.locked') : t('status.active') }}
                  </span>
                </td>
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="openPermissions(u)" :title="t('users.view_permissions')" class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center hover:bg-amber-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                    </button>
                    <button @click="openEdit(u)" :title="t('common.edit')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z" /></svg>
                    </button>
                    <button
                      @click="toggleLock(u)"
                      :disabled="u.id === auth.user?.id"
                      :title="u.id === auth.user?.id ? t('users.cannot_lock_self') : (u.is_locked ? t('common.unlock') : t('common.lock'))"
                      class="w-7 h-7 rounded-lg bg-accent text-brand-800 flex items-center justify-center hover:bg-accent-dark hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-accent disabled:hover:text-brand-800"
                    >
                      <svg v-if="u.is_locked" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                    </button>
                    <button @click="resettingId = u.id; newPassword = ''; newPasswordConfirm = ''" :title="t('common.reset_password')" class="w-7 h-7 rounded-lg bg-indigo-500 text-white flex items-center justify-center hover:bg-indigo-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                    </button>
                    <button @click="deletingId = u.id" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !filtered.length">
                <td colspan="6" class="py-10 text-center text-faint">{{ search ? t('users.empty_search') : t('users.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        </template>
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
          <!-- Stacked on phones: two selects side by side leave ~140px each in
               a modal at 375px, which truncates "Operations & HR Manager" to
               the point of being unreadable. -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

          <!-- Additional roles are additive: they widen what the account above
               can do, they never take the base role's access away. -->
          <div v-if="can('roles', 'update')" class="space-y-1.5 pt-3 border-t border-line">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('users.additional_roles') }}</label>
            <p class="text-xs text-faint">{{ t('users.additional_roles_hint') }}</p>
            <div v-if="!allRoles.length" class="text-xs text-faint py-2">{{ t('users.no_custom_roles') }}</div>
            <div v-else class="max-h-44 overflow-y-auto rounded-xl border border-line divide-y divide-line mt-1">
              <label v-for="r in allRoles" :key="r.id"
                class="flex items-start gap-2.5 px-3 py-2 text-sm cursor-pointer hover:bg-surface-2 transition">
                <input type="checkbox" :checked="selectedRoleIds.includes(r.id)" @change="toggleRole(r.id)"
                  class="mt-0.5 rounded border-line text-brand focus:ring-brand/30" />
                <span class="min-w-0">
                  <span class="font-medium text-fg">{{ r.name }}</span>
                  <span v-if="!r.is_active" class="badge badge-neutral ml-1.5">{{ t('roles.inactive') }}</span>
                  <span class="block text-xs text-faint truncate">
                    {{ r.description || t('roles.no_description') }} · {{ t('roles.n_permissions', { count: r.permission_count }) }}
                  </span>
                </span>
              </label>
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

    <!-- Effective permissions: what this account can actually do, and which
         role each grant came from. -->
    <Modal v-if="permissionsFor" :title="t('users.permissions_title', { name: permissionsFor.user.name })" wide @close="permissionsFor = null">
      <div class="p-6 space-y-5">
        <div v-if="permissionsLoading" class="py-10 text-center text-sm text-faint">{{ t('common.loading') }}</div>
        <template v-else-if="permissionsFor.data">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('users.role') }}</span>
            <span class="badge badge-info">{{ roleLabels[permissionsFor.data.user.role] || permissionsFor.data.user.role }}</span>
            <template v-for="r in permissionsFor.data.roles" :key="r.id">
              <span class="badge" :class="r.is_active ? 'badge-success' : 'badge-neutral'">{{ r.name }}</span>
            </template>
          </div>

          <div v-if="!Object.keys(permissionsFor.data.effective).length" class="text-sm text-faint py-6 text-center">
            {{ t('users.no_permissions') }}
          </div>
          <div v-else class="table-wrap overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ t('roles.module') }}</th>
                  <th>{{ t('users.effective') }}</th>
                  <th>{{ t('users.granted_by') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(list, key) in permissionsFor.data.effective" :key="key">
                  <td class="font-medium text-fg whitespace-nowrap">{{ moduleLabel(key) }}</td>
                  <td>
                    <span v-for="a in list" :key="a" class="badge badge-info mr-1 capitalize">{{ t(`roles.ability_${a}`) }}</span>
                  </td>
                  <td class="text-xs text-faint">
                    <span v-if="permissionsFor.data.baseline[key]">{{ t('users.from_base_role') }}</span>
                    <span v-if="permissionsFor.data.from_roles[key]">
                      {{ [...new Set(Object.values(permissionsFor.data.from_roles[key]).flat())].join(', ') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-if="permissionsFor.data.hidden_modules.length" class="text-xs text-faint">
            {{ t('users.hidden_modules', { list: permissionsFor.data.hidden_modules.map(moduleLabel).join(', ') }) }}
          </p>
          <p class="text-xs text-faint">{{ t('users.permissions_note') }}</p>
        </template>
      </div>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="selectedIds.length === 1 ? t('users.delete_confirm_title_one') : t('users.delete_confirm_title_other', { count: selectedIds.length })"
      :message="t('users.delete_confirm_message')"
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
  </AppLayout>
</template>
