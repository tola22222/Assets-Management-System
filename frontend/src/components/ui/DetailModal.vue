<script setup>
import { useI18n } from 'vue-i18n'
import Modal from './Modal.vue'
import StatusBadge from './StatusBadge.vue'

// Read-only details view for a table row. None of the workflow resources
// (disposals, verifications, transfers, assignments) expose a `show` endpoint —
// their index already returns the full record with its relations — so View
// renders from the row in memory rather than fetching one.
//
// `rows` is a list of { label, value, type? }. Labels arrive already translated
// so each page keeps its t('…') calls as static literals that check-i18n can see.
defineProps({
  title: { type: String, required: true },
  rows: { type: Array, default: () => [] },
})
defineEmits(['close'])

const { t } = useI18n()
</script>

<template>
  <Modal :title="title" @close="$emit('close')">
    <dl class="divide-y divide-line">
      <div
        v-for="(row, i) in rows"
        :key="i"
        class="grid grid-cols-1 sm:grid-cols-[150px_minmax(0,1fr)] gap-x-6 gap-y-1 px-6 py-3.5"
      >
        <dt class="text-sm font-semibold text-fg">{{ row.label }}</dt>
        <dd class="text-sm text-muted min-w-0">
          <StatusBadge v-if="row.type === 'status' && row.value" :status="row.value" />
          <a
            v-else-if="row.type === 'image' && row.value"
            :href="row.value"
            target="_blank"
            rel="noopener"
            class="inline-block"
          >
            <img :src="row.value" class="w-24 h-24 rounded-xl object-cover border border-line" alt="" />
          </a>
          <span v-else-if="row.type === 'code' && row.value" class="id-chip">{{ row.value }}</span>
          <!-- Free text (a disposal reason, a verification remark) can run long
               and carry line breaks, so it wraps instead of being truncated the
               way the table cell does. -->
          <p v-else-if="row.type === 'multiline'" class="whitespace-pre-wrap break-words">{{ row.value || '—' }}</p>
          <span v-else :class="row.type === 'capitalize' ? 'capitalize' : ''">{{ row.value || '—' }}</span>
        </dd>
      </div>
    </dl>
    <div class="flex justify-end border-t border-line px-6 py-4">
      <button type="button" class="btn-ghost" @click="$emit('close')">{{ t('common.close') }}</button>
    </div>
  </Modal>
</template>
