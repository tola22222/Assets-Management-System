<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import TablePagination from '../../components/ui/TablePagination.vue'
import { useToastStore } from '../../stores/toast'

const { t, locale } = useI18n()
const toast = useToastStore()
const router = useRouter()
const notifications = ref([])
const loading = ref(true)

// The API paginates at 20 and there are far more rows than that on a live
// account; the old page read only the first page and offered no way to reach
// the rest. `page` is zero-based to match TablePagination, and one higher on
// the wire because Laravel's paginator is one-based.
const page = ref(0)
const perPage = ref(20)
const total = ref(0)

// Counted by the server across every page, not just the rows on screen, so the
// header total agrees with the sidebar bell.
const unreadCount = ref(0)

// Each notification type gets an icon and a tone. The message already says what
// happened, so the tile carries the category without a text label — which also
// keeps 14 type names out of the translation files.
const BELL = 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'
const TYPES = {
  asset_registered:  { tone: 'brand',   icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z' },
  asset_assigned:    { tone: 'info',    icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z' },
  asset_verified:    { tone: 'success', icon: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z' },
  asset_flagged:     { tone: 'danger',  icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z' },
  qr_scan:           { tone: 'neutral', icon: 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM13.5 14.25h2.25v2.25H13.5zM18 18h2.25v2.25H18z' },
  transfer_request:  { tone: 'info',    icon: 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-9L21 7.5m0 0L16.5 3M21 7.5H7.5' },
  transfer_approved: { tone: 'success', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  disposal_request:  { tone: 'warning', icon: 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0' },
  disposal_approved: { tone: 'success', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  disposal_rejected: { tone: 'danger',  icon: 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  return_request:    { tone: 'info',    icon: 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3' },
  return_approved:   { tone: 'success', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  scheduled_report:  { tone: 'neutral', icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
}

// Tones reuse the exact hexes behind .badge-*, so a notification tile and a
// status badge of the same meaning are the same colour.
const TONES = {
  brand:   'bg-brand-50 text-brand-700',
  success: 'bg-[#e2f0e8] text-[#236b43]',
  warning: 'bg-[#faecd9] text-[#915a1a]',
  info:    'bg-[#e3ecf7] text-[#2b5a8c]',
  danger:  'bg-[#fcebeb] text-[#a13b3b]',
  neutral: 'bg-surface-3 text-muted',
}

const meta = (n) => TYPES[n.type] || { tone: 'neutral', icon: BELL }
const iconFor = (n) => meta(n).icon
const toneFor = (n) => TONES[meta(n).tone]

// Relative time via Intl, keyed off the running locale — no strings to
// translate, and it follows the Settings language picker automatically.
function timeAgo(iso) {
  if (!iso) return ''
  const seconds = Math.round((new Date(iso) - new Date()) / 1000)
  const abs = Math.abs(seconds)
  const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]]
  try {
    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })
    for (const [unit, size] of units) {
      if (abs >= size) return rtf.format(Math.round(seconds / size), unit)
    }
    return rtf.format(Math.round(seconds), 'second')
  } catch {
    // A locale Intl does not know must not blank the timestamp.
    return new Date(iso).toLocaleString()
  }
}

const fullDate = (iso) => (iso ? new Date(iso).toLocaleString() : '')

async function loadUnreadCount() {
  try {
    const { data } = await http.get('/notifications/unread-count')
    unreadCount.value = data.count ?? 0
  } catch {
    // The header chip is decoration; a failure here must not break the list.
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await http.get('/notifications', { params: { page: page.value + 1 } })
    notifications.value = data.data
    total.value = data.total ?? data.data.length
    perPage.value = data.per_page ?? 20
  } catch (e) {
    notifications.value = []
    toast.error(errorMessage(e, t('notifications.load_failed')))
  } finally {
    loading.value = false
  }
}

function goToPage(next) {
  page.value = next
  load()
}

async function markRead(n) {
  try {
    await http.post(`/notifications/${n.id}/mark-read`)
    n.is_read = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch (e) {
    // Only flip the dot once the server agrees, or the row lies about itself.
    toast.error(errorMessage(e, t('notifications.mark_failed')))
  }
}

async function markAllRead() {
  try {
    await http.post('/notifications/mark-all-read')
    toast.success(t('notifications.marked_all_read'))
    unreadCount.value = 0
    await load()
  } catch (e) {
    toast.error(errorMessage(e, t('notifications.mark_failed')))
  }
}

// Most notifications carry no url, but the scheduled-report one does and it was
// previously inert. Route on the path only: the stored value is an absolute
// URL built server-side, which in dev points at the API's port rather than the
// SPA's, so following it verbatim would leave the app.
function openTarget(n) {
  if (!n.is_read) markRead(n)
  if (!n.url) return
  try {
    router.push(new URL(n.url, window.location.origin).pathname)
  } catch {
    // A malformed stored url should do nothing, not throw mid-click.
  }
}

onMounted(() => {
  load()
  loadUnreadCount()
})
</script>

<template>
  <AppLayout>
    <!-- Same shell as System Settings and User Management: the title block sits
         on the canvas with a brand tile beside it, the feed lives in its own
         card below. -->
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-5">

      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-start gap-4 min-w-0">
          <div class="w-11 h-11 rounded-2xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-[var(--shadow-card)]">
            <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="BELL" /></svg>
          </div>
          <div class="min-w-0">
            <h1 class="font-display text-2xl font-bold text-fg tracking-tight flex items-center gap-2.5">
              {{ t('notifications.title') }}
              <span v-if="unreadCount" class="badge badge-danger">{{ unreadCount }}</span>
            </h1>
            <p class="text-muted text-sm mt-1">{{ t('notifications.subtitle') }}</p>
          </div>
        </div>
        <!-- Only offered while something is actually unread. -->
        <button v-if="unreadCount" @click="markAllRead" class="btn-ghost flex-shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /><polyline points="23 6 13 17 11 15" /></svg>
          {{ t('common.mark_all_read') }}
        </button>
      </div>

      <div class="card overflow-hidden">
        <!-- Skeleton keeps the card's shape during the first fetch rather than
             collapsing it to a thin strip. -->
        <div v-if="loading" class="divide-y divide-line">
          <div v-for="n in 5" :key="n" class="p-4 flex items-start gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-surface-2 animate-pulse flex-shrink-0"></div>
            <div class="flex-1 space-y-2 pt-1">
              <div class="h-3.5 rounded bg-surface-2 animate-pulse" :style="{ width: (55 + (n * 7) % 35) + '%' }"></div>
              <div class="h-3 w-24 rounded bg-surface-2 animate-pulse"></div>
            </div>
          </div>
        </div>

        <div v-else-if="notifications.length" class="divide-y divide-line">
          <div
            v-for="n in notifications"
            :key="n.id"
            class="relative flex items-start gap-3.5 p-4 transition-colors"
            :class="[n.is_read ? '' : 'bg-brand/[0.04] dark:bg-white/[0.03]', n.url ? 'cursor-pointer hover:bg-surface-2' : '']"
            @click="n.url && openTarget(n)"
          >
            <!-- Unread rows carry a brand bar on the leading edge as well as the
                 tint, so the state survives for anyone who cannot separate the
                 two background shades. -->
            <span v-if="!n.is_read" class="absolute left-0 top-0 bottom-0 w-[3px] bg-brand"></span>

            <span class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" :class="toneFor(n)">
              <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="iconFor(n)" /></svg>
            </span>

            <div class="min-w-0 flex-1">
              <p class="text-sm text-fg break-words" :class="n.is_read ? '' : 'font-semibold'">{{ n.message }}</p>
              <!-- Relative time reads faster in a feed; the exact timestamp
                   stays available on hover. -->
              <p class="text-xs text-faint mt-1" :title="fullDate(n.created_at)">{{ timeAgo(n.created_at) }}</p>
            </div>

            <button
              v-if="!n.is_read"
              @click.stop="markRead(n)"
              :title="t('common.mark_read')"
              :aria-label="t('common.mark_read')"
              class="btn-icon flex-shrink-0"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </button>
          </div>
        </div>

        <div v-else class="p-6">
          <div class="rounded-xl border border-dashed border-line bg-surface-2/50 px-4 py-12 text-center">
            <svg class="w-8 h-8 mx-auto text-faint mb-2.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="BELL" /></svg>
            <p class="text-sm text-faint">{{ t('notifications.empty') }}</p>
          </div>
        </div>

        <!-- The page size is fixed by the server, so the rows-per-page selector
             is hidden and only the pager shows. -->
        <TablePagination
          v-if="total > perPage"
          :count="total"
          :page="page"
          :rows-per-page="perPage"
          :rows-per-page-options="[]"
          @update:page="goToPage"
        />
      </div>
    </div>
  </AppLayout>
</template>
