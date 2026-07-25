<script setup>
import { ref, onMounted, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: users, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/users', { filterKeys: ['role', 'is_locked'] })
const { create, update, destroy } = useApiCrud('/users', { entityName: t('users.entity'), refetch: fetchPage })
const { confirm } = useConfirm()
const toast = useToastStore()

const staffList = ref([])
const showModal = ref(null)
const editingId = ref(null)
const resettingId = ref(null)
const newPassword = ref('')
const newPasswordConfirm = ref('')

const roleLabels = computed(() => ({
  operations_hr_manager: t('users.role_admin'), staff: t('users.role_staff'),
  executive_director: t('users.role_executive_director'), finance_manager: t('users.role_finance_manager'),
}))
const roleColors = {
  operations_hr_manager: 'purple',
  executive_director: 'amber',
  finance_manager: 'teal',
  staff: 'blue',
}

const emptyForm = () => ({ name: '', email: '', password: '', role: 'staff', staff_id: '' })
const form = reactive(emptyForm())

const headers = computed(() => [
  { title: t('common.name'), key: 'name', sortable: true },
  { title: t('common.email'), key: 'email', sortable: true },
  { title: t('users.role'), key: 'role', sortable: true },
  { title: t('common.status'), key: 'is_locked', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 160 },
])

async function loadStaff() {
  const { data } = await http.get('/staff', { params: { per_page: 100 } })
  staffList.value = data.data ?? data
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
  await fetchPage()
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

async function handleDelete(row) {
  const ok = await confirm({
    title: t('confirm.delete_title'),
    message: t('confirm.delete_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  try {
    await destroy(row.id)
  } catch (e) {
    toast.error(e.response?.data?.message || t('users.delete_failed'))
  }
}

onMounted(() => {
  fetchPage()
  loadStaff()
})
</script>

<template>
  <v-container fluid class="pa-0">
    <AppPageHeader
      :title="t('users.title')"
      :subtitle="t('users.subtitle')"
      :actions="[{ label: t('users.new'), icon: 'mdi-plus', onClick: openCreate }]"
    />

    <AppDataTable
      :headers="headers"
      :items="users"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="sortByVuetify"
      :search="search"
      :search-label="t('users.search_placeholder')"
      :empty-text="search ? t('users.empty_search') : t('users.empty')"
      @update:search="setSearch"
      @update:options="handleOptions"
    >
      <template #filters>
        <v-select
          v-model="filters.role"
          :label="t('users.role')"
          :items="[
            { title: t('common.all'), value: '' },
            { title: t('users.role_admin'), value: 'operations_hr_manager' },
            { title: t('users.role_executive_director'), value: 'executive_director' },
            { title: t('users.role_finance_manager'), value: 'finance_manager' },
            { title: t('users.role_staff'), value: 'staff' },
          ]"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 220px"
          @update:model-value="applyFilters"
        />
        <v-select
          v-model="filters.is_locked"
          :label="t('common.status')"
          :items="[
            { title: t('common.all'), value: '' },
            { title: t('status.active'), value: 'false' },
            { title: t('status.locked'), value: 'true' },
          ]"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 220px"
          @update:model-value="applyFilters"
        />
        <v-btn v-if="hasActiveFilters" variant="text" size="small" @click="clearFilters">{{ t('common.clear_filters') }}</v-btn>
      </template>

      <template #item.role="{ item }">
        <v-chip size="small" :color="roleColors[item.role]" variant="tonal">{{ roleLabels[item.role] || item.role }}</v-chip>
      </template>
      <template #item.is_locked="{ item }">
        <v-chip size="small" :color="item.is_locked ? 'error' : 'success'" variant="tonal">
          {{ item.is_locked ? t('status.locked') : t('status.active') }}
        </v-chip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-end ga-1">
          <v-btn icon="mdi-pencil-outline" size="small" variant="text" color="primary" title="Edit" @click="openEdit(item)" />
          <v-btn
            :icon="item.is_locked ? 'mdi-lock-open-variant-outline' : 'mdi-lock-outline'"
            size="small"
            variant="text"
            :title="item.is_locked ? t('common.unlock') : t('common.lock')"
            @click="toggleLock(item)"
          />
          <v-btn
            icon="mdi-key-outline"
            size="small"
            variant="text"
            color="indigo"
            :title="t('common.reset_password')"
            @click="resettingId = item.id; newPassword = ''; newPasswordConfirm = ''"
          />
          <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" title="Delete" @click="handleDelete(item)" />
        </div>
      </template>
    </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="editingId ? t('users.edit_title') : t('users.create_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-text-field v-model="form.name" :label="t('users.name_required')" required />
        <v-text-field v-model="form.email" :label="t('users.email_required')" type="email" required />
        <v-text-field v-if="!editingId" v-model="form.password" :label="t('users.password')" type="password" minlength="8" required />
        <div class="d-flex ga-4">
          <v-select
            v-model="form.role"
            :label="t('users.role_required')"
            class="flex-grow-1"
            :items="[
              { title: t('users.role_staff'), value: 'staff' },
              { title: t('users.role_admin'), value: 'operations_hr_manager' },
              { title: t('users.role_executive_director'), value: 'executive_director' },
              { title: t('users.role_finance_manager'), value: 'finance_manager' },
            ]"
          />
          <v-select
            v-model="form.staff_id"
            :label="t('users.link_to_staff')"
            class="flex-grow-1"
            :items="[{ title: t('users.none'), value: '' }, ...staffList.map((s) => ({ title: s.full_name, value: s.id }))]"
          />
        </div>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">
          {{ editingId ? t('users.save_changes') : t('users.create_button') }}
        </v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>

  <Modal v-if="resettingId" :title="t('users.reset_password_title')" @close="resettingId = null">
    <v-form @submit.prevent="submitPasswordReset">
      <v-card-text class="d-flex flex-column ga-1">
        <v-text-field v-model="newPassword" :label="t('users.new_password')" type="password" minlength="8" required />
        <v-text-field v-model="newPasswordConfirm" :label="t('users.confirm_password')" type="password" minlength="8" required />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat">{{ t('users.reset_password_title') }}</v-btn>
        <v-btn variant="text" @click="resettingId = null">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
