<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PepyDialog from '../ui/PepyDialog.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  label: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'confirm'])

const { t } = useI18n()
const reason = ref('')

watch(
  () => props.modelValue,
  (open) => { if (open) reason.value = '' }
)
</script>

<template>
  <PepyDialog
    :model-value="modelValue"
    :title="t('common.reject')"
    icon="mdi-close-circle-outline"
    confirm-color="error"
    :confirm-text="t('common.reject')"
    :loading="loading"
    max-width="480"
    @update:model-value="(v) => emit('update:modelValue', v)"
    @confirm="emit('confirm', reason)"
  >
    <v-textarea v-model="reason" :label="label || t('common.rejection_reason')" rows="3" auto-grow />
  </PepyDialog>
</template>
