<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  page: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  perPage: { type: Number, required: true },
  total: { type: Number, required: true },
})
const emit = defineEmits(['update:page', 'update:perPage'])

const pageSizes = [10, 25, 50, 100]
</script>

<template>
  <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3 border-t border-line text-sm">
    <div class="flex items-center gap-2 text-faint">
      <span>{{ t('common.rows_per_page') }}</span>
      <select
        :value="perPage"
        @change="emit('update:perPage', Number($event.target.value))"
        class="filter-select !py-1.5"
      >
        <option v-for="n in pageSizes" :key="n" :value="n">{{ n }}</option>
      </select>
    </div>

    <p class="text-faint sm:ml-2">
      {{ t('common.showing_range', {
        from: total === 0 ? 0 : (page - 1) * perPage + 1,
        to: Math.min(page * perPage, total),
        total,
      }) }}
    </p>

    <div class="flex items-center gap-1 sm:ml-auto">
      <button class="btn-ghost btn-sm !px-2.5" :disabled="page <= 1" @click="emit('update:page', 1)" :title="t('common.first_page')">«</button>
      <button class="btn-ghost btn-sm !px-2.5" :disabled="page <= 1" @click="emit('update:page', page - 1)" :title="t('common.previous')">‹</button>
      <span class="px-3 text-fg font-medium">{{ t('common.page_of', { page, lastPage: Math.max(lastPage, 1) }) }}</span>
      <button class="btn-ghost btn-sm !px-2.5" :disabled="page >= lastPage" @click="emit('update:page', page + 1)" :title="t('common.next')">›</button>
      <button class="btn-ghost btn-sm !px-2.5" :disabled="page >= lastPage" @click="emit('update:page', lastPage)" :title="t('common.last_page')">»</button>
    </div>
  </div>
</template>
