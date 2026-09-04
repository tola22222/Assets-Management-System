<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Mirrors Material UI's TablePagination: a right-aligned toolbar carrying the
// rows-per-page select, an "x–y of z" range label, and prev/next buttons.
// `page` is zero-based, as MUI's is.
const props = defineProps({
  count: { type: Number, required: true },
  page: { type: Number, required: true },
  rowsPerPage: { type: Number, required: true },
  // An empty array hides the selector — used where the page size is fixed by
  // the server and the client cannot change it.
  rowsPerPageOptions: { type: Array, default: () => [10, 25, 50, 100] },
})
const emit = defineEmits(['update:page', 'update:rowsPerPage'])

const lastPage = computed(() => Math.max(0, Math.ceil(props.count / props.rowsPerPage) - 1))
const from = computed(() => (props.count === 0 ? 0 : props.page * props.rowsPerPage + 1))
const to = computed(() => Math.min(props.count, (props.page + 1) * props.rowsPerPage))

function onRowsPerPage(event) {
  emit('update:rowsPerPage', Number(event.target.value))
  // Changing the page size makes the current offset meaningless, so go back to
  // the first page — the same thing MUI does.
  emit('update:page', 0)
}
</script>

<template>
  <div class="flex flex-wrap items-center justify-center sm:justify-end gap-x-6 gap-y-3 px-2 py-2.5 min-h-[52px] text-sm text-muted border-t border-line">
    <div v-if="rowsPerPageOptions.length" class="flex items-center gap-2">
      <select
        :aria-label="t('pagination.rows_per_page')"
        :title="t('pagination.rows_per_page')"
        :value="rowsPerPage"
        @change="onRowsPerPage"
        class="bg-surface-2 border border-line rounded-md pl-2.5 pr-7 py-1 text-sm text-fg cursor-pointer transition
               focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15"
      >
        <option v-for="n in rowsPerPageOptions" :key="n" :value="n">{{ n }}</option>
      </select>
    </div>

    <p class="whitespace-nowrap tabular-nums">
      {{ t('pagination.displayed', { from, to, count }) }}
    </p>

    <div class="flex items-center gap-1.5">
      <button
        type="button"
        class="btn-icon"
        :disabled="page <= 0"
        :aria-label="t('pagination.previous')"
        :title="t('pagination.previous')"
        @click="emit('update:page', page - 1)"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6" /></svg>
      </button>
      <button
        type="button"
        class="btn-icon"
        :disabled="page >= lastPage"
        :aria-label="t('pagination.next')"
        :title="t('pagination.next')"
        @click="emit('update:page', page + 1)"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg>
      </button>
    </div>
  </div>
</template>
