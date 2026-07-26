<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import PepyDialog from '../ui/PepyDialog.vue'

defineProps({ modelValue: { type: Boolean, default: false } })
const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const router = useRouter()

const OPTIONS = [
  { type: 'transfer', icon: 'mdi-swap-horizontal', route: 'asset-transfers', color: 'primary' },
  { type: 'checkout', icon: 'mdi-account-arrow-right-outline', route: 'asset-assignments', color: 'secondary' },
  { type: 'checkin', icon: 'mdi-account-arrow-left-outline', route: 'asset-returns', color: 'success' },
  { type: 'disposal', icon: 'mdi-delete-outline', route: 'asset-disposals', color: 'error' },
]

function choose(option) {
  emit('update:modelValue', false)
  router.push({ name: option.route, query: { create: '1' } })
}
</script>

<template>
  <PepyDialog
    :model-value="modelValue"
    :title="t('stock_motion.new_movement')"
    :subtitle="t('stock_motion.new_movement_subtitle')"
    icon="mdi-swap-horizontal-bold"
    max-width="480"
    hide-actions
    @update:model-value="(v) => emit('update:modelValue', v)"
  >
    <div class="d-flex flex-column ga-2">
      <v-btn
        v-for="opt in OPTIONS"
        :key="opt.type"
        variant="tonal"
        :color="opt.color"
        size="large"
        class="justify-start"
        :prepend-icon="opt.icon"
        @click="choose(opt)"
      >
        {{ t(`stock_motion.type_${opt.type}`) }}
      </v-btn>
    </div>
  </PepyDialog>
</template>
