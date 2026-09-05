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
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

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
// The Roles pane's create action is rendered by the page header (so it matches
// New User exactly), and reaches into the panel through this ref.
const rolesPanel = ref(null)

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
// Role chips reuse the shared .badge-* variants rather than raw Tailwind
// palette colours: those sat outside the brand palette and kept their light
// pastel background in dark mode. Brand green marks the account that
// administers the system, so the strongest role reads strongest.
const roleColors = {
  operations_hr_manager: 'badge-brand',
  executive_director: 'badge-warning',
  finance_manager: 'badge-info',
  staff: 'badge-neutral',
}

// Avatar fallback when an account has no photo — same two-letter treatment as
// the header avatar in AppLayout.
function initials(name) {
  if (!name) return 'U'
  return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase()
}


// Icon paths shared by the page header, the pane switch and the empty state,
// from the same outline set the sidebar uses, as in AppLayout.
const I = {
  users: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
  shield: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
}

const panes = computed(() => [
  { key: 'users', label: t('users.tab_users'), icon: I.users },
  { key: 'roles', label: t('users.tab_roles'), icon: I.shield },
])

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

// Pagination is the last step, applied to the finished list, so search
// and sort still consider every row rather than just the page on screen.
const { page, rowsPerPage, total, paged } = usePagination(filtered)
</script>

<template>
  <AppLayout>
    <!-- Page shell matches System Settings: the title block sits on the canvas
         with a brand tile beside it, and the content lives in its own card
         below, rather than everything sharing one oversized panel. -->
    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-5">

      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-start gap-4 min-w-0">
          <div class="w-11 h-11 rounded-2xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-[var(--shadow-card)]">
            <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="I.users" /></svg>
          </div>
          <div class="min-w-0">
            <h1 class="font-display text-2xl font-bold text-fg tracking-tight">{{ t('users.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('users.subtitle') }}</p>
          </div>
        </div>
        <button v-if="tab === 'users'" @click="openCreate" class="btn-primary flex-shrink-0">
          <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
          {{ t('users.new') }}
        </button>
        <button v-else-if="can('roles', 'create')" @click="rolesPanel?.openCreate()" class="btn-primary flex-shrink-0">
          <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
          {{ t('roles.new') }}
        </button>
      </div>

      <!-- Accounts and the roles they can hold are two views of the same
           thing, so they live side by side rather than on separate pages. The
           selected pane takes the sidebar's brand fill, the same signal the
           settings rail uses. -->
      <div v-if="can('roles', 'view')" class="inline-flex items-center gap-1 bg-surface-2 border border-line rounded-xl p-1">
        <button
          v-for="pane in panes"
          :key="pane.key"
          type="button"
          @click="tab = pane.key"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors duration-150"
          :class="tab === pane.key ? 'bg-brand text-white shadow-[var(--shadow-card)]' : 'text-muted hover:text-fg'"
          :aria-current="tab === pane.key ? 'page' : undefined"
        >
          <svg class="w-4 h-4" :class="tab === pane.key ? 'text-white' : 'text-faint'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="pane.icon" /></svg>
          {{ pane.label }}
        </button>
      </div>

      <RolesPanel v-if="tab === 'roles'" ref="rolesPanel" />

      <template v-else>
        <div class="table-wrap">
          <!-- Search and the bulk action share the card's own toolbar. Bulk
               delete only exists while rows are ticked, so it belongs beside
               the selection rather than in the page header. -->
          <div class="table-toolbar">
            <div class="flex-1 min-w-[240px]">
              <SearchInput v-model="search" :placeholder="t('users.search_placeholder')" />
            </div>
            <div v-if="selectedIds.length" class="flex items-center gap-2 flex-shrink-0">
              <button @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                {{ t('users.delete_selected') }} ({{ selectedIds.length }})
              </button>
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
                <!-- Skeleton rows keep the table's shape during the first load
                     instead of collapsing the card down to its header. -->
                <tr v-for="n in (loading ? 4 : 0)" :key="'sk' + n">
                  <td colspan="6"><div class="h-8 rounded-lg bg-surface-2 animate-pulse"></div></td>
                </tr>
                <tr v-for="u in paged" :key="u.id">
                  <td>
                    <input type="checkbox" :checked="selectedIds.includes(u.id)" @change="toggleSelect(u.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                  </td>
                  <!-- Avatar beside the name, the same treatment the header
                       gives the signed-in account, so a person is recognisable
                       at a glance in a list of otherwise similar rows. -->
                  <td>
                    <div class="flex items-center gap-2.5 min-w-0">
                      <span class="w-8 h-8 rounded-full bg-brand text-white flex items-center justify-center text-[11px] font-bold flex-shrink-0 overflow-hidden">
                        <img v-if="u.photo_url" :src="u.photo_url" alt="" class="w-full h-full object-cover" />
                        <span v-else>{{ initials(u.name) }}</span>
                      </span>
                      <span class="font-semibold text-fg truncate">{{ u.name }}</span>
                    </div>
                  </td>
                  <td class="text-muted">{{ u.email }}</td>
                  <td>
                    <span class="badge" :class="roleColors[u.role]">{{ roleLabels[u.role] || u.role }}</span>
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
                      <button @click="openPermissions(u)" :title="t('users.view_permissions')" :aria-label="t('users.view_permissions')" class="btn-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="I.shield" /></svg>
                      </button>
                      <button @click="openEdit(u)" :title="t('common.edit')" :aria-label="t('common.edit')" class="btn-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                      </button>
                      <button
                        @click="toggleLock(u)"
                        :disabled="u.id === auth.user?.id"
                        :title="u.id === auth.user?.id ? t('users.cannot_lock_self') : (u.is_locked ? t('common.unlock') : t('common.lock'))"
                        :aria-label="u.is_locked ? t('common.unlock') : t('common.lock')"
                        class="btn-icon"
                      >
                        <svg v-if="u.is_locked" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                      </button>
                      <button @click="resettingId = u.id; newPassword = ''; newPasswordConfirm = ''" :title="t('common.reset_password')" :aria-label="t('common.reset_password')" class="btn-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                      </button>
                      <button @click="deletingId = u.id" :title="t('common.delete')" :aria-label="t('common.delete')" class="btn-icon-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <!-- Empty state gets the dashed placeholder used elsewhere in
                     the app, not one line of grey text adrift in the card. -->
                <tr v-if="!loading && !filtered.length">
                  <td colspan="6" class="p-5">
                    <div class="rounded-xl border border-dashed border-line bg-surface-2/50 px-4 py-10 text-center">
                      <svg class="w-7 h-7 mx-auto text-faint mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="I.users" /></svg>
                      <p class="text-sm text-faint">{{ search ? t('users.empty_search') : t('users.empty') }}</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- Pagination is part of the same card, divided from the rows by a
               rule rather than floating underneath it. -->
          <div class="border-t border-line">
            <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
          </div>
        </div>
      </template>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('users.edit_title') : t('users.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div>
            <label class="label">{{ t('users.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div>
            <label class="label">{{ t('users.email_required') }}</label>
            <input v-model="form.email" type="email" required class="input" />
          </div>
          <div v-if="!editingId">
            <label class="label">{{ t('users.password') }}</label>
            <input v-model="form.password" type="password" minlength="8" required class="input" />
          </div>
          <!-- Stacked on phones: two selects side by side leave ~140px each in
               a modal at 375px, which truncates "Operations & HR Manager" to
               the point of being unreadable. -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">{{ t('users.role_required') }}</label>
              <select v-model="form.role" class="select">
                <option value="staff">{{ t('users.role_staff') }}</option>
                <option value="operations_hr_manager">{{ t('users.role_admin') }}</option>
                <option value="executive_director">{{ t('users.role_executive_director') }}</option>
                <option value="finance_manager">{{ t('users.role_finance_manager') }}</option>
              </select>
            </div>
            <div>
              <label class="label">{{ t('users.link_to_staff') }}</label>
              <select v-model="form.staff_id" class="select">
                <option value="">{{ t('users.none') }}</option>
                <option v-for="s in staffList" :key="s.id" :value="s.id">{{ s.full_name }}</option>
              </select>
            </div>
          </div>

          <!-- Additional roles are additive: they widen what the account above
               can do, they never take the base role's access away. -->
          <div v-if="can('roles', 'update')" class="pt-3 border-t border-line">
            <label class="label">{{ t('users.additional_roles') }}</label>
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
          <div>
            <label class="label">{{ t('users.new_password') }}</label>
            <input v-model="newPassword" type="password" minlength="8" required class="input" />
          </div>
          <div>
            <label class="label">{{ t('users.confirm_password') }}</label>
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
