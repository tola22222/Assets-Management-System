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

// Master/detail: the sidebar picks a role, the panel shows its grants. The
// selection is held by id and re-resolved after every reload, so it survives a
// save/duplicate/toggle without pointing at a stale copy of the role.
const selectedId = ref(null)
const selected = computed(() => roles.value.find((r) => r.id === selectedId.value) || null)

const form = reactive({ name: '', description: '', is_active: true, permissions: {} })

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [r, c] = await Promise.all([http.get('/roles'), http.get('/roles/catalogue')])
    roles.value = r.data
    modules.value = c.data.modules
    abilities.value = c.data.abilities
    if (!roles.value.some((x) => x.id === selectedId.value)) {
      selectedId.value = roles.value[0]?.id ?? null
    }
  } catch (e) {
    roles.value = []
    loadError.value = errorMessage(e, t('roles.load_failed'))
    toast.error(loadError.value)
  } finally {
    loading.value = false
  }
}

// The matrix is laid out one block per catalogue group, which is where the
// column headers live too.
const groupedModules = computed(() => {
  const out = []
  modules.value.forEach((m) => {
    let g = out.find((x) => x.name === m.group)
    if (!g) { g = { name: m.group, modules: [] }; out.push(g) }
    g.modules.push(m)
  })
  return out
})

function granted(role, moduleKey, ability) {
  return (role?.permissions?.[moduleKey] || []).includes(ability)
}

function initials(name) {
  return (name || '')
    .split(/\s+/)
    .filter((w) => /[a-z0-9]/i.test(w))
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase() || '?'
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

onMounted(load)
defineExpose({ reload: load, openCreate })
</script>

<template>
  <div>
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-5 items-start">
      <div class="h-80 rounded-xl bg-surface-2 animate-pulse"></div>
      <div class="h-80 rounded-xl bg-surface-2 animate-pulse"></div>
    </div>

    <div v-else-if="loadError" class="card p-6 text-center space-y-3">
      <p class="text-sm text-red-600 dark:text-red-400">{{ loadError }}</p>
      <button @click="load" class="btn-ghost btn-sm">{{ t('common.retry') }}</button>
    </div>

    <!-- Master / detail: role list beside the selected role's permissions. -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-5 items-start">

      <!-- ── Role list ─────────────────────────────────────────────── -->
      <div class="card p-3">
        <SearchInput v-model="search" :placeholder="t('roles.search_placeholder')" />

        <div class="flex flex-col gap-0.5 mt-3 max-h-[520px] overflow-y-auto">
          <button
            v-for="r in filtered"
            :key="r.id"
            type="button"
            @click="selectedId = r.id"
            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-left transition"
            :class="r.id === selectedId ? 'bg-brand/10' : 'hover:bg-surface-2'"
          >
            <span
              class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0"
              :class="r.id === selectedId ? 'bg-brand text-white' : 'bg-surface-2 text-muted'"
            >{{ initials(r.name) }}</span>
            <span class="flex-1 min-w-0">
              <span class="block text-[13px] font-medium truncate" :class="r.is_active ? 'text-fg' : 'text-faint'">{{ r.name }}</span>
            </span>
            <!-- Grant count doubles as the "how big is this role" cue. -->
            <span class="tag flex-shrink-0" :title="t('roles.permissions')">{{ r.permission_count }}</span>
          </button>

          <p v-if="!filtered.length" class="px-2.5 py-8 text-center">
            <span class="block text-sm font-medium text-muted">{{ search ? t('roles.empty_search') : t('roles.empty') }}</span>
            <span class="block text-xs text-faint mt-1">{{ search ? t('roles.empty_search_hint') : t('roles.empty_hint') }}</span>
          </p>
        </div>
      </div>

      <!-- ── Selected role ─────────────────────────────────────────── -->
      <div v-if="selected" class="card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-5 sm:px-6 py-5 border-b border-line">
          <div class="flex items-center gap-3.5 min-w-0">
            <span class="w-[52px] h-[52px] rounded-full bg-brand text-white flex items-center justify-center text-base font-bold flex-shrink-0">
              {{ initials(selected.name) }}
            </span>
            <div class="min-w-0">
              <h3 class="flex flex-wrap items-center gap-2 font-bold text-fg">
                <span class="truncate">{{ selected.name }}</span>
                <span v-if="selected.is_system" class="badge badge-info">{{ t('roles.built_in') }}</span>
                <span class="badge" :class="selected.is_active ? 'badge-success' : 'badge-neutral'">
                  {{ selected.is_active ? t('status.active') : t('roles.inactive') }}
                </span>
              </h3>
              <p class="text-sm text-muted mt-0.5 truncate" :title="selected.description || ''">
                {{ selected.description || t('roles.no_description') }}
              </p>
              <p class="text-xs text-faint mt-0.5">
                {{ t('roles.n_permissions', { count: selected.permission_count }) }} · {{ selected.users_count }} {{ t('roles.users') }}
              </p>
            </div>
          </div>

          <!-- The old table's row actions, moved onto the selected role. -->
          <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
            <button v-if="can('roles', 'update')" @click="openEdit(selected)" class="btn-ghost btn-sm">{{ t('common.edit') }}</button>
            <button v-if="can('roles', 'create')" @click="duplicate(selected)" class="btn-ghost btn-sm">{{ t('roles.duplicate') }}</button>
            <button v-if="can('roles', 'update') && !selected.is_system" @click="toggleActive(selected)" class="btn-ghost btn-sm">
              {{ selected.is_active ? t('roles.deactivate') : t('roles.activate') }}
            </button>
            <button v-if="can('roles', 'delete') && !selected.is_system" @click="deleting = selected" class="btn-danger btn-sm">{{ t('common.delete') }}</button>
          </div>
        </div>

        <div v-if="!selected.is_active" class="px-5 sm:px-6 py-3 border-b border-line bg-surface-2 text-sm text-muted">
          {{ t('roles.inactive_grants_nothing') }}
        </div>

        <!-- Permission matrix: one block per catalogue group, a column per
             ability. It scrolls sideways rather than squeezing six ability
             columns into a phone width. -->
        <div class="p-5 sm:p-6 overflow-x-auto">
          <div class="min-w-[760px]">
            <div v-for="group in groupedModules" :key="group.name" class="mb-7 last:mb-0">
              <div
                class="grid items-center gap-2 pb-2 border-b border-line"
                :style="{ gridTemplateColumns: `220px repeat(${abilities.length}, minmax(0,1fr))` }"
              >
                <span class="text-[13px] font-bold text-fg">{{ group.name }}</span>
                <span
                  v-for="a in abilities"
                  :key="a"
                  class="text-[11px] font-semibold uppercase tracking-wide text-faint"
                >{{ t(`roles.ability_${a}`) }}</span>
              </div>

              <div
                v-for="m in group.modules"
                :key="m.key"
                class="grid items-center gap-2 py-3 border-b border-line/60"
                :style="{ gridTemplateColumns: `220px repeat(${abilities.length}, minmax(0,1fr))` }"
              >
                <span class="text-[13px] font-medium text-fg truncate" :title="m.label">{{ m.label }}</span>
                <span
                  v-for="a in abilities"
                  :key="a"
                  class="flex items-center gap-1.5 text-xs"
                  :class="granted(selected, m.key, a) ? 'text-fg font-medium' : 'text-faint'"
                >
                  <span
                    class="w-4 h-4 rounded flex items-center justify-center flex-shrink-0 border"
                    :class="granted(selected, m.key, a) ? 'bg-brand border-brand' : 'bg-surface border-line'"
                  >
                    <svg v-if="granted(selected, m.key, a)" class="w-2.5 h-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  </span>
                  {{ granted(selected, m.key, a) ? t('roles.yes') : t('roles.no') }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="card p-12 text-center">
        <p class="text-sm text-muted">{{ t('roles.select_role') }}</p>
      </div>
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
        <!-- Sticky, unlike the other modals' action bars: Modal.vue puts the
             whole slot inside its scroll area, and the permission matrix makes
             this form several screens tall, so a footer in normal flow left the
             save button below the fold with no hint it was there. -->
        <div class="sticky bottom-0 z-10 flex items-center gap-3 border-t border-line bg-surface px-6 py-4">
          <button type="submit" :disabled="saving" class="btn-primary">
            {{ saving ? t('roles.saving') : (editing ? t('roles.save_changes') : t('roles.create_button')) }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog
      v-if="confirmingSave"
      tone="primary"
      :title="t('roles.confirm_save_title')"
      :message="t('roles.confirm_save_message', { grants: confirmingSave.grants, holders: confirmingSave.holders })"
      :confirm-label="t('common.save')"
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
