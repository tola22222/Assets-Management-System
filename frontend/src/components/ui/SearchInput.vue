<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: null },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="relative">
    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-faint">
      <svg class="w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
      </svg>
    </span>
    <input
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :placeholder="placeholder ?? t('common.search_placeholder')"
      class="input !pl-10"
    />
    <button
      v-if="modelValue"
      type="button"
      @click="$emit('update:modelValue', '')"
      class="absolute inset-y-0 right-0 flex items-center pr-3 text-faint hover:text-fg"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>
  </div>
</template>
