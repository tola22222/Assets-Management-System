<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'

// A dropdown you can type into. Typing shows every option whose text CONTAINS
// what was typed, anywhere, ignoring case — "lap" finds "Dell Laptop", "0928"
// finds "PEY-SR-FAF-0928" — unlike a native <select>, whose type-ahead only
// jumps to the first option that STARTS with the keys pressed.
//
// Looks like the control it replaces (pass inputClass="select" or
// "filter-select"); the list is teleported to <body> so a dialog's scroll
// area never clips it.
//
// options: [{ value, label, sub? }] — sub (e.g. an asset code) is searched too.
// multiple: v-model is an array; each option toggles with a checkbox and the
// list stays open so several can be picked in one go.
const props = defineProps({
  modelValue: { type: [String, Number, Array, null], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  // An option for "nothing chosen" at the top (e.g. "All locations"); its value is ''.
  emptyLabel: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  inputClass: { type: String, default: 'select' },
  ariaLabel: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'change'])
const { t } = useI18n()

const open = ref(false)
const query = ref('')
const highlighted = ref(0)
const inputEl = ref(null)
const listEl = ref(null)
const listStyle = ref({})

// .select paints its own chevron; any other class (.input, .filter-select)
// only gets one on a real <select>, so draw the same chevron for those.
const needsChevron = computed(() => !props.inputClass.split(/\s+/).includes('select'))

const norm = (s) => String(s ?? '').toLowerCase().replace(/\s+/g, ' ').trim()

const allOptions = computed(() => [
  ...(props.emptyLabel ? [{ value: '', label: props.emptyLabel, isEmpty: true }] : []),
  ...props.options,
])
const picked = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []).map(String))
const isSelected = (o) => (props.multiple ? picked.value.includes(String(o.value)) : String(o.value) === String(props.modelValue ?? ''))
const selected = computed(() => allOptions.value.find((o) => String(o.value) === String(props.modelValue ?? '')))
const selectedText = computed(() => {
  if (props.multiple) return props.options.filter(isSelected).map((o) => o.label).join(', ')
  const o = selected.value
  if (!o || (o.isEmpty && !props.emptyLabel)) return ''
  return o.sub ? `${o.label} (${o.sub})` : o.label
})

// Contains-matching on the label and the sub-label; every typed word must appear.
const filtered = computed(() => {
  const words = norm(query.value).split(' ').filter(Boolean)
  if (!words.length) return allOptions.value
  return allOptions.value.filter((o) => {
    if (o.isEmpty) return false
    const hay = norm(`${o.label} ${o.sub ?? ''}`)
    return words.every((w) => hay.includes(w))
  })
})

function place() {
  const r = inputEl.value?.getBoundingClientRect()
  if (!r) return
  const below = window.innerHeight - r.bottom
  const up = below < 260 && r.top > below
  listStyle.value = {
    position: 'fixed', left: `${r.left}px`, width: `${r.width}px`, zIndex: 1000,
    ...(up ? { bottom: `${window.innerHeight - r.top + 4}px` } : { top: `${r.bottom + 4}px` }),
  }
}

function openList() {
  if (props.disabled || open.value) return
  query.value = ''
  open.value = true
  const i = filtered.value.findIndex(isSelected)
  highlighted.value = Math.max(i, 0)
  place()
  nextTick(scrollToHighlighted)
}

function close() {
  open.value = false
  query.value = ''
}

function choose(o) {
  if (!o) return
  if (props.multiple) {
    const next = isSelected(o)
      ? props.modelValue.filter((v) => String(v) !== String(o.value))
      : [...(Array.isArray(props.modelValue) ? props.modelValue : []), o.value]
    emit('update:modelValue', next)
    nextTick(() => emit('change', next))
    return
  }
  if (String(o.value) !== String(props.modelValue ?? '')) {
    emit('update:modelValue', o.value)
    nextTick(() => emit('change', o.value))
  }
  close()
}

function scrollToHighlighted() {
  listEl.value?.querySelector(`[data-index="${highlighted.value}"]`)?.scrollIntoView({ block: 'nearest' })
}

function onKey(e) {
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
    e.preventDefault()
    if (!open.value) return openList()
    const n = filtered.value.length
    if (!n) return
    highlighted.value = (highlighted.value + (e.key === 'ArrowDown' ? 1 : -1) + n) % n
    nextTick(scrollToHighlighted)
  } else if (e.key === 'Enter') {
    if (open.value) {
      e.preventDefault()
      choose(filtered.value[highlighted.value])
    }
  } else if (e.key === 'Escape') {
    if (open.value) {
      e.stopPropagation()
      close()
    }
  } else if (e.key === 'Tab') {
    close()
  }
}

function onInput(e) {
  query.value = e.target.value
  highlighted.value = 0
  if (!open.value) openList()
}

watch(query, () => nextTick(scrollToHighlighted))

// Keep the floating list attached while anything scrolls or resizes.
function reposition() {
  if (open.value) place()
}
watch(open, (isOpen) => {
  const method = isOpen ? 'addEventListener' : 'removeEventListener'
  window[method]('scroll', reposition, true)
  window[method]('resize', reposition)
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', reposition, true)
  window.removeEventListener('resize', reposition)
})
</script>

<template>
  <div class="relative">
    <input
      ref="inputEl"
      type="text"
      :class="[inputClass, needsChevron ? 'pr-9' : '', disabled ? '' : 'cursor-pointer']"
      class="w-full"
      :value="open ? query : selectedText"
      :placeholder="open ? (selectedText || placeholder || t('common.search')) : (placeholder || emptyLabel)"
      :disabled="disabled"
      :aria-label="ariaLabel || placeholder || emptyLabel"
      role="combobox"
      :aria-expanded="open"
      :aria-multiselectable="multiple || undefined"
      autocomplete="off"
      @focus="openList"
      @click="openList"
      @input="onInput"
      @keydown="onKey"
      @blur="close"
    />
    <svg v-if="needsChevron" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-faint" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
    <!-- Carries `required` for the browser's own form validation. -->
    <input
      v-if="required"
      :value="multiple ? (picked.length ? '1' : '') : (modelValue ?? '')"
      required
      tabindex="-1"
      aria-hidden="true"
      class="absolute left-1/2 bottom-0 w-px h-px opacity-0 pointer-events-none"
      @focus="inputEl?.focus()"
    />
    <Teleport to="body">
      <ul
        v-if="open"
        ref="listEl"
        :style="listStyle"
        role="listbox"
        class="max-h-60 overflow-y-auto rounded-lg border border-line bg-surface shadow-lg py-1 text-[13px]"
        @mousedown.prevent
      >
        <li
          v-for="(o, i) in filtered"
          :key="String(o.value) + '|' + i"
          :data-index="i"
          role="option"
          :aria-selected="isSelected(o)"
          class="px-3 py-2 cursor-pointer flex items-center justify-between gap-2"
          :class="[
            i === highlighted ? 'bg-brand/10 text-fg' : 'text-fg hover:bg-surface-2',
            !multiple && isSelected(o) ? 'font-semibold' : '',
            o.isEmpty ? 'text-muted' : '',
          ]"
          @mouseenter="highlighted = i"
          @click="choose(o)"
        >
          <span class="flex items-center gap-2 min-w-0">
            <input v-if="multiple" type="checkbox" :checked="isSelected(o)" tabindex="-1" class="rounded border-line text-brand focus:ring-brand/30 pointer-events-none" />
            <span class="truncate">{{ o.label }}</span>
          </span>
          <span v-if="o.sub" class="font-mono text-[12px] text-muted flex-shrink-0">{{ o.sub }}</span>
        </li>
        <li v-if="!filtered.length" class="px-3 py-2 text-faint">{{ t('common.no_results') }}</li>
      </ul>
    </Teleport>
  </div>
</template>
