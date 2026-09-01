<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import PermissionMatrix from '../../components/ui/PermissionMatrix.vue'
import { useTableSearch } from '../../composables/useTableSearch'
import { useToastStore } from '../../stores/toast'
import { usePermissions } from '../../composables/usePermissions'

const { t } = useI18n()
const toast = useToastStore()
const { can } = usePermissions()

const roles = ref([])
const modules = ref([])
const abilities = ref([])
const loading = ref(true)
const loadError = ref('')

const { search, filtered } = useTableSearch(roles, ['name', 'description'])

const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const deleting = ref(null)
const confirmingSave = ref(null)
const viewing = ref(null)

const form = reactive({ name: '', description: '', is_active: true, permissions: {} })

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [r, c] = await Promise.all([http.get('/roles'), http.get('/roles/catalogue')])
    roles.value = r.data
    modules.value = c.data.modules
    abilities.value = c.data.abilities
  } catch (e) {
    roles.value = []
    loadError.value = errorMessage(e, t('roles.load_failed'))
    toast.error(loadError.value)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', description: '', is_active: true, permissions: {} })
  showModal.value = true
}

function openEdit(role) {
  editing.value = role
  Object.assign(form, {
    name: role.name,
    description: role.description || '',
    is_active: role.is_active,
    permissions: JSON.parse(JSON.stringify(role.permissions || {})),
  })
  showModal.value = true
}

const grantCount = computed(() =>
  Object.values(form.permissions).reduce((n, l) => n + (l?.length || 0), 0)
)

function requestSave() {
  if (!form.name.trim()) {
    toast.error(t('roles.name_required'))
    return
  }
  // Save confirmation: a permission change takes effect on every holder's next
  // request, so the count of affected accounts is shown before committing.
  confirmingSave.value = {
    grants: grantCount.value,
    holders: editing.value?.users_count ?? 0,
  }
}

async function save() {
  confirmingSave.value = null
  saving.value = true
  try {
    const payload = {
      name: form.name.trim(),
      description: form.description || null,
      is_active: form.is_active,
      permissions: form.permissions,
    }
    if (editing.value) {
      await http.put(`/roles/${editing.value.id}`, payload)
      toast.success(t('roles.updated'))
    } else {
      await http.post('/roles', payload)
      toast.success(t('roles.created'))
    }
    showModal.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e, t('roles.save_failed')))
  } finally {
    saving.value = false
  }
}

async function toggleActive(role) {
  try {
    await http.post(`/roles/${role.id}/toggle`)
    toast.success(role.is_active ? t('roles.deactivated') : t('roles.activated'))
    await load()
  } catch (e) {
    toast.error(errorMessage(e, t('roles.toggle_failed')))
  }
}

async function duplicate(role) {
  try {
    await http.post(`/roles/${role.id}/duplicate`)
    toast.success(t('roles.duplicated'))
    await load()
  } catch (e) {
    toast.error(errorMessage(e, t('roles.duplicate_failed')))
  }
}

async function confirmDelete() {
  try {
    await http.delete(`/roles/${deleting.value.id}`)
    toast.success(t('roles.deleted'))
    await load()
  } catch (e) {
    toast.error(errorMessage(e, t('roles.delete_failed')))
  } finally {
    deleting.value = null
  }
}

function moduleLabel(key) {
  return modules.value.find((m) => m.key === key)?.label || key
}

onMounted(load)
defineExpose({ reload: load })
</script>

<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="font-display text-xl font-bold text-fg">{{ t('roles.title') }}</h2>
        <p class="text-muted text-sm mt-1">{{ t('roles.subtitle') }}</p>
      </div>
      <button v-if="can('roles', 'create')" @click="openCreate" class="btn-primary btn-sm flex-shrink-0">
        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        {{ t('roles.new') }}
      </button>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <div class="flex-1 min-w-[260px]">
        <SearchInput v-model="search" :placeholder="t('roles.search_placeholder')" />
      </div>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 4" :key="i" class="h-14 rounded-xl bg-surface-2 animate-pulse"></div>
    </div>

    <div v-else-if="loadError" class="card p-6 text-center space-y-3">
      <p class="text-sm text-red-600 dark:text-red-400">{{ loadError }}</p>
      <button @click="load" class="btn-ghost btn-sm">{{ t('common.retry') }}</button>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ t('roles.role') }}</th>
            <th>{{ t('common.description') }}</th>
            <th class="text-center">{{ t('roles.permissions') }}</th>
            <th class="text-center">{{ t('roles.users') }}</th>
            <th class="text-center">{{ t('common.status') }}</th>
            <th class="text-right">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in filtered" :key="r.id">
            <td>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-fg">{{ r.name }}</span>
                <span v-if="r.is_system" class="badge badge-info">{{ t('roles.built_in') }}</span>
              </div>
            </td>
            <td class="text-muted max-w-sm truncate" :title="r.description">{{ r.description || '—' }}</td>
            <td class="text-center">
              <button @click="viewing = r" class="tag hover:bg-surface-3 transition">{{ r.permission_count }}</button>
            </td>
            <td class="text-center text-muted">{{ r.users_count }}</td>
            <td class="text-center">
              <span class="badge" :class="r.is_active ? 'badge-success' : 'badge-neutral'">
                {{ r.is_active ? t('status.active') : t('roles.inactive') }}
              </span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1.5">
                <button @click="viewing = r" :title="t('common.view')" class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center hover:bg-amber-600 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                </button>
                <button v-if="can('roles', 'update')" @click="openEdit(r)" :title="t('common.edit')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z" /></svg>
                </button>
                <button v-if="can('roles', 'create')" @click="duplicate(r)" :title="t('roles.duplicate')" class="w-7 h-7 rounded-lg bg-indigo-500 text-white flex items-center justify-center hover:bg-indigo-600 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" /></svg>
                </button>
                <button v-if="can('roles', 'update') && !r.is_system" @click="toggleActive(r)"
                  :title="r.is_active ? t('roles.deactivate') : t('roles.activate')"
                  class="w-7 h-7 rounded-lg text-white flex items-center justify-center transition"
                  :class="r.is_active ? 'bg-slate-500 hover:bg-slate-600' : 'bg-emerald-500 hover:bg-emerald-600'">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" /></svg>
                </button>
                <button v-if="can('roles', 'delete') && !r.is_system" @click="deleting = r" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!filtered.length">
            <td colspan="6" class="py-12 text-center">
              <p class="text-muted text-sm font-medium">{{ search ? t('roles.empty_search') : t('roles.empty') }}</p>
              <p class="text-xs text-faint mt-1">{{ search ? t('roles.empty_search_hint') : t('roles.empty_hint') }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / edit -->
    <Modal v-if="showModal" :title="editing ? t('roles.edit_title', { name: editing.name }) : t('roles.create_title')" wide @close="showModal = false">
      <form @submit.prevent="requestSave">
        <div class="p-6 space-y-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="label">{{ t('roles.name') }} <span class="text-red-500">*</span></label>
              <input v-model="form.name" required maxlength="100" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="label">{{ t('common.status') }}</label>
              <label class="flex items-center gap-2.5 text-sm text-muted h-[42px]">
                <input type="checkbox" v-model="form.is_active" :disabled="editing?.is_system"
                  class="rounded border-line text-brand focus:ring-brand/30 disabled:opacity-50" />
                <span>{{ t('roles.is_active_hint') }}</span>
              </label>
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="label">{{ t('common.description') }}</label>
            <textarea v-model="form.description" rows="2" class="textarea" :placeholder="t('roles.description_placeholder')"></textarea>
          </div>

          <div v-if="editing?.is_system" class="rounded-xl border border-line bg-surface-2 px-4 py-3 text-sm text-muted">
            {{ t('roles.system_role_note') }}
          </div>

          <div class="pt-2 border-t border-line">
            <h3 class="font-bold text-fg mb-1">{{ t('roles.permissions') }}</h3>
            <p class="text-sm text-faint mb-4">{{ t('roles.permissions_subtitle') }}</p>
            <PermissionMatrix v-model="form.permissions" :modules="modules" :abilities="abilities" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="saving" class="btn-primary">
            {{ saving ? t('roles.saving') : (editing ? t('roles.save_changes') : t('roles.create_button')) }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <!-- Effective permissions of one role -->
    <Modal v-if="viewing" :title="t('roles.view_title', { name: viewing.name })" wide @close="viewing = null">
      <div class="p-6 space-y-4">
        <p class="text-sm text-muted">{{ viewing.description || t('roles.no_description') }}</p>
        <div v-if="!viewing.permission_count" class="text-sm text-faint py-6 text-center">{{ t('roles.no_permissions') }}</div>
        <div v-else class="table-wrap overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>{{ t('roles.module') }}</th><th>{{ t('roles.permissions') }}</th></tr></thead>
            <tbody>
              <tr v-for="(list, key) in viewing.permissions" :key="key">
                <td class="font-medium text-fg">{{ moduleLabel(key) }}</td>
                <td>
                  <span v-for="a in list" :key="a" class="badge badge-info mr-1 capitalize">{{ t(`roles.ability_${a}`) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Modal>

    <ConfirmDialog
      v-if="confirmingSave"
      :title="t('roles.confirm_save_title')"
      :message="t('roles.confirm_save_message', { grants: confirmingSave.grants, holders: confirmingSave.holders })"
      @confirm="save"
      @cancel="confirmingSave = null"
    />
    <ConfirmDialog
      v-if="deleting"
      :title="t('roles.confirm_delete_title', { name: deleting.name })"
      :message="deleting.users_count
        ? t('roles.confirm_delete_assigned', { count: deleting.users_count })
        : t('confirm.cannot_be_undone')"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </div>
</template>
