<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, required: true }, // [{ value, label }]
  // Set when every option is mutually exclusive and one must always be
  // selected (e.g. a year picker) — there's no "unfiltered" state to offer.
  hideAll: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex items-center gap-2 flex-wrap">
    <button v-if="!hideAll" type="button" @click="$emit('update:modelValue', '')"
      class="filter-pill" :class="{ 'filter-pill-active': modelValue === '' }">
      {{ t('common.all') }}
    </button>
    <button type="button" v-for="o in options" :key="o.value" @click="$emit('update:modelValue', o.value)"
      class="filter-pill" :class="{ 'filter-pill-active': String(modelValue) === String(o.value) }">
      {{ o.label }}
    </button>
  </div>
</template>
