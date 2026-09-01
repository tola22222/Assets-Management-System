<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  modules: { type: Array, required: true },   // [{ key, label, group }]
  abilities: { type: Array, required: true }, // ['view','create',...]
  modelValue: { type: Object, required: true }, // { moduleKey: ['view', ...] }
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

// Create/Read/Update/Delete are meaningless without access to the module, so
// View is forced on whenever any of them is ticked and cannot be unticked
// while they are. The server normalises the same way, so the matrix can never
// show a state the backend would silently rewrite.
const REQUIRES_VIEW = ['create', 'read', 'update', 'delete']

const grouped = computed(() => {
  const out = []
  props.modules.forEach((m) => {
    let g = out.find((x) => x.name === m.group)
    if (!g) { g = { name: m.group, modules: [] }; out.push(g) }
    g.modules.push(m)
  })
  return out
})

const totalPossible = computed(() => props.modules.length * props.abilities.length)
const totalGranted = computed(() =>
  Object.values(props.modelValue).reduce((n, list) => n + (list?.length || 0), 0)
)

function has(moduleKey, ability) {
  return (props.modelValue[moduleKey] || []).includes(ability)
}

function commit(next) {
  emit('update:modelValue', next)
}

function lockedOn(moduleKey, ability) {
  // View is locked on while a dependent ability is ticked.
  return ability === 'view' && REQUIRES_VIEW.some((a) => has(moduleKey, a))
}

function toggle(moduleKey, ability) {
  if (props.disabled || lockedOn(moduleKey, ability)) return
  const next = { ...props.modelValue }
  const current = new Set(next[moduleKey] || [])

  if (current.has(ability)) {
    current.delete(ability)
    // Dropping View drops everything that depended on it.
    if (ability === 'view') REQUIRES_VIEW.forEach((a) => current.delete(a))
  } else {
    current.add(ability)
    if (REQUIRES_VIEW.includes(ability)) current.add('view')
  }

  const list = props.abilities.filter((a) => current.has(a))
  if (list.length) next[moduleKey] = list
  else delete next[moduleKey]
  commit(next)
}

function moduleState(moduleKey) {
  const n = (props.modelValue[moduleKey] || []).length
  if (n === 0) return 'none'
  return n === props.abilities.length ? 'all' : 'some'
}

function toggleModule(moduleKey) {
  if (props.disabled) return
  const next = { ...props.modelValue }
  if (moduleState(moduleKey) === 'all') delete next[moduleKey]
  else next[moduleKey] = [...props.abilities]
  commit(next)
}

function toggleGroup(group) {
  if (props.disabled) return
  const next = { ...props.modelValue }
  const allOn = group.modules.every((m) => moduleState(m.key) === 'all')
  group.modules.forEach((m) => {
    if (allOn) delete next[m.key]
    else next[m.key] = [...props.abilities]
  })
  commit(next)
}

function toggleColumn(ability) {
  if (props.disabled) return
  const next = { ...props.modelValue }
  const allOn = props.modules.every((m) => (next[m.key] || []).includes(ability))
  props.modules.forEach((m) => {
    const current = new Set(next[m.key] || [])
    if (allOn) {
      current.delete(ability)
      if (ability === 'view') REQUIRES_VIEW.forEach((a) => current.delete(a))
    } else {
      current.add(ability)
      if (REQUIRES_VIEW.includes(ability)) current.add('view')
    }
    const list = props.abilities.filter((a) => current.has(a))
    if (list.length) next[m.key] = list
    else delete next[m.key]
  })
  commit(next)
}

function selectAll() {
  if (props.disabled) return
  const next = {}
  props.modules.forEach((m) => { next[m.key] = [...props.abilities] })
  commit(next)
}

function clearAll() {
  if (props.disabled) return
  commit({})
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-muted">
        {{ t('roles.granted_count', { granted: totalGranted, total: totalPossible }) }}
      </p>
      <div class="flex items-center gap-2">
        <button type="button" :disabled="disabled" @click="selectAll" class="btn-ghost btn-sm">{{ t('roles.select_all') }}</button>
        <button type="button" :disabled="disabled" @click="clearAll" class="btn-ghost btn-sm">{{ t('roles.clear_all') }}</button>
      </div>
    </div>

    <div class="table-wrap overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th class="min-w-[220px]">{{ t('roles.module') }}</th>
            <th v-for="a in abilities" :key="a" class="text-center whitespace-nowrap">
              <button type="button" :disabled="disabled" @click="toggleColumn(a)"
                class="capitalize hover:text-brand-600 dark:hover:text-brand-300 disabled:cursor-not-allowed"
                :title="t('roles.toggle_column', { ability: t(`roles.ability_${a}`) })">
                {{ t(`roles.ability_${a}`) }}
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in grouped" :key="group.name">
            <tr class="bg-surface-2">
              <td :colspan="abilities.length + 1" class="py-2">
                <button type="button" :disabled="disabled" @click="toggleGroup(group)"
                  class="text-xs font-bold uppercase tracking-wide text-muted hover:text-brand-600 dark:hover:text-brand-300 disabled:cursor-not-allowed">
                  {{ group.name }}
                </button>
              </td>
            </tr>
            <tr v-for="m in group.modules" :key="m.key">
              <td>
                <button type="button" :disabled="disabled" @click="toggleModule(m.key)"
                  class="flex items-center gap-2 text-left disabled:cursor-not-allowed">
                  <span class="w-3.5 h-3.5 rounded border flex items-center justify-center flex-shrink-0"
                    :class="moduleState(m.key) === 'all' ? 'bg-brand border-brand'
                      : moduleState(m.key) === 'some' ? 'border-brand bg-brand/25' : 'border-line'">
                    <span v-if="moduleState(m.key) === 'all'" class="text-white text-[9px] leading-none">✓</span>
                  </span>
                  <span class="font-medium text-fg">{{ m.label }}</span>
                </button>
              </td>
              <td v-for="a in abilities" :key="a" class="text-center">
                <input
                  type="checkbox"
                  :checked="has(m.key, a)"
                  :disabled="disabled || lockedOn(m.key, a)"
                  :title="lockedOn(m.key, a) ? t('roles.view_locked') : ''"
                  @change="toggle(m.key, a)"
                  class="rounded border-line text-brand focus:ring-brand/30 disabled:opacity-50"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <p class="text-xs text-faint">{{ t('roles.matrix_hint') }}</p>
  </div>
</template>
