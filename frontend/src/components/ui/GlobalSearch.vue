<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import http from '../../api/http'
import { usePermissions } from '../../composables/usePermissions'

// Header search over GET /api/search (Api\SearchController), which already
// trims the result set by role — staff and users only come back for OPM.
// Picking a result opens that module's list page with its table search
// pre-filled via ?q= (see useTableSearch), since no module has a detail page.
const { t } = useI18n()
const router = useRouter()
const { canSee } = usePermissions()

// Display order, plus how each result type reads and where it leads.
// `q` is the value handed to the target page's table search, so it must be a
// field that page's useTableSearch actually covers.
const TYPES = {
  assets: { to: '/assets', title: (r) => r.name, meta: (r) => r.asset_code, q: (r) => r.asset_code || r.name },
  staff: { to: '/staff', title: (r) => r.full_name, meta: (r) => r.position, q: (r) => r.full_name },
  users: { to: '/users', title: (r) => r.name, meta: (r) => r.email, q: (r) => r.email || r.name },
  locations: { to: '/locations', title: (r) => r.name, meta: (r) => r.code, q: (r) => r.name },
  programs: { to: '/programs', title: (r) => r.name, meta: () => null, q: (r) => r.name },
  categories: { to: '/categories', title: (r) => r.name, meta: (r) => r.short_name, q: (r) => r.name },
  suppliers: { to: '/suppliers', title: (r) => r.name, meta: (r) => r.phone, q: (r) => r.name },
}

const q = ref('')
const results = ref(null)
const loading = ref(false)
const open = ref(false)
const activeIndex = ref(-1)
const root = ref(null)
const input = ref(null)

// Inline field from md up. On phones the header has no room for it next to the
// theme, bell and profile controls, so the field lives in a panel that a
// search icon opens instead.
const wideQuery = window.matchMedia('(min-width: 768px)')
const wide = ref(wideQuery.matches)
const expanded = ref(false)
function onWideChange(e) {
  wide.value = e.matches
  expanded.value = false
}

let timer = null
let requestSeq = 0

const term = computed(() => q.value.trim())

// Results grouped for display, dropping any module this account can't open so
// a result never leads to a page that would only 403.
const groups = computed(() => {
  if (!results.value) return []
  return Object.entries(TYPES)
    .filter(([type]) => results.value[type]?.length && canSee(type))
    .map(([type, cfg]) => ({
      type,
      label: t(`search.type_${type}`),
      items: results.value[type].map((row) => ({
        key: `${type}-${row.id}`,
        title: cfg.title(row) || cfg.q(row),
        meta: cfg.meta(row),
        to: { path: cfg.to, query: { q: cfg.q(row) } },
      })),
    }))
})
const flatItems = computed(() => groups.value.flatMap((g) => g.items))

watch(q, () => {
  clearTimeout(timer)
  activeIndex.value = -1
  if (term.value.length < 2) {
    requestSeq++
    results.value = null
    loading.value = false
    return
  }
  loading.value = true
  timer = setTimeout(runSearch, 250)
})

async function runSearch() {
  const seq = ++requestSeq
  try {
    const { data } = await http.get('/search', { params: { q: term.value } })
    // A slower, older request must not overwrite a newer one's results.
    if (seq === requestSeq) results.value = data
  } catch {
    if (seq === requestSeq) results.value = {}
  } finally {
    if (seq === requestSeq) loading.value = false
  }
}

function close() {
  open.value = false
  expanded.value = false
  activeIndex.value = -1
}

async function focusInput() {
  if (!wide.value) expanded.value = true
  await nextTick()
  input.value?.focus()
}

function toggleExpanded() {
  if (expanded.value) close()
  else focusInput()
}

function reset() {
  q.value = ''
  close()
  input.value?.blur()
}

function go(item) {
  router.push(item.to)
  reset()
}

function viewAll() {
  if (term.value.length < 2) return
  router.push({ name: 'search', query: { q: term.value } })
  reset()
}

function onKeydown(e) {
  const count = flatItems.value.length
  if (e.key === 'ArrowDown' && count) {
    e.preventDefault()
    open.value = true
    activeIndex.value = (activeIndex.value + 1) % count
  } else if (e.key === 'ArrowUp' && count) {
    e.preventDefault()
    activeIndex.value = activeIndex.value <= 0 ? count - 1 : activeIndex.value - 1
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const item = flatItems.value[activeIndex.value]
    item ? go(item) : viewAll()
  } else if (e.key === 'Escape') {
    if (q.value) q.value = ''
    else reset()
  }
}

// Ctrl/Cmd+K or "/" focuses the box from anywhere, unless the user is already
// typing into a field.
function onGlobalKeydown(e) {
  const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target?.tagName) || e.target?.isContentEditable
  if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !typing)) {
    e.preventDefault()
    focusInput()
  }
}

function onPointerDown(e) {
  if (root.value && !root.value.contains(e.target)) close()
}

onMounted(() => {
  document.addEventListener('keydown', onGlobalKeydown)
  document.addEventListener('pointerdown', onPointerDown)
  wideQuery.addEventListener('change', onWideChange)
})
onBeforeUnmount(() => {
  clearTimeout(timer)
  document.removeEventListener('keydown', onGlobalKeydown)
  document.removeEventListener('pointerdown', onPointerDown)
  wideQuery.removeEventListener('change', onWideChange)
})

</script>

<template>
  <!-- Sits on the left of the header, beside the menu button. Wide: the field
       takes four fifths of the header's leftover space (basis 0, grows; the
       layout's spacer takes the rest), between a 14rem floor and a 36rem cap,
       so the fixed controls on the right are sized first. Narrow: just an
       icon; its panel is positioned against the (sticky) header, since neither
       wrapper here is positioned. -->
  <div :class="wide ? 'grow-[4] basis-0 min-w-[14rem] max-w-xl' : 'flex-shrink-0'">
    <div ref="root" :class="wide ? 'relative w-full' : ''">
      <button
        v-if="!wide"
        type="button"
        class="w-9 h-9 rounded-xl flex items-center justify-center transition-colors"
        :class="expanded ? 'bg-surface-2 text-fg' : 'text-muted hover:bg-surface-2 hover:text-fg'"
        :title="t('search.title')"
        :aria-label="t('search.title')"
        :aria-expanded="expanded"
        @click="toggleExpanded"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
      </button>

      <div
        v-if="wide || expanded"
        :class="wide ? '' : 'absolute left-4 sm:left-6 top-full mt-2 w-[min(26rem,calc(100vw-2rem))] bg-surface border border-line rounded-xl shadow-[var(--shadow-pop)] overflow-hidden z-50'"
      >
        <div class="relative" :class="wide ? '' : 'm-2'">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-faint">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
          </span>
          <input
            ref="input"
            v-model="q"
            type="search"
            :placeholder="t('search.global_placeholder')"
            :aria-label="t('search.title')"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open && term.length >= 2"
            aria-controls="global-search-results"
            autocomplete="off"
            class="input !h-9 !pl-9 !pr-9 !border-0 bg-surface-2 focus:!ring-2 focus:ring-brand/15 [&::-webkit-search-cancel-button]:hidden"
            @focus="open = true"
            @input="open = true"
            @keydown="onKeydown"
          />
          <button
            v-if="q"
            type="button"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-faint hover:text-fg"
            :aria-label="t('search.clear')"
            @click="q = ''; input?.focus()"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div
          v-if="open && term.length >= 2"
          id="global-search-results"
          role="listbox"
          :class="wide
            ? 'absolute left-0 top-full mt-2 w-full min-w-[22rem] max-w-[calc(100vw-2rem)] bg-surface border border-line rounded-xl shadow-[var(--shadow-pop)] overflow-hidden z-50'
            : 'border-t border-line'"
        >
          <div class="max-h-[60vh] overflow-y-auto py-1.5">
            <p v-if="loading && !groups.length" class="px-4 py-6 text-center text-sm text-faint">{{ t('common.loading') }}</p>
            <p v-else-if="!groups.length" class="px-4 py-6 text-center text-sm text-faint">{{ t('common.no_results') }}</p>

            <div v-for="group in groups" :key="group.type" class="py-1">
              <p class="px-4 pt-1.5 pb-1 text-[11px] font-semibold text-faint uppercase tracking-wide">{{ group.label }}</p>
              <button
                v-for="item in group.items"
                :key="item.key"
                type="button"
                role="option"
                :aria-selected="flatItems[activeIndex] === item"
                class="w-full flex items-center justify-between gap-3 px-4 py-2 text-left text-sm transition-colors"
                :class="flatItems[activeIndex] === item ? 'bg-brand/10 text-fg' : 'text-fg hover:bg-surface-2'"
                @mouseenter="activeIndex = flatItems.indexOf(item)"
                @click="go(item)"
              >
                <span class="truncate">{{ item.title }}</span>
                <span v-if="item.meta" class="text-xs text-faint truncate flex-shrink-0 max-w-[45%]">{{ item.meta }}</span>
              </button>
            </div>
          </div>

          <button
            type="button"
            class="w-full flex items-center justify-between px-4 py-2.5 border-t border-line text-sm font-medium text-brand dark:text-brand-200 hover:bg-surface-2 transition-colors"
            @click="viewAll"
          >
            <span class="truncate">{{ t('search.view_all', { q: term }) }}</span>
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
