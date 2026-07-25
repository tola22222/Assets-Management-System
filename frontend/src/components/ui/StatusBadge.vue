<script setup>
import { useI18n } from 'vue-i18n'

const { t, te } = useI18n()
defineProps({ status: { type: String, required: true } })

const COLORS = {
  approved: 'success',
  active: 'success',
  completed: 'success',
  stock_in: 'success',
  pending: 'warning',
  assigned: 'info',
  transfer: 'info',
  rejected: 'error',
  disposed: 'error',
  overdue: 'error',
  returned: undefined,
}
</script>

<template>
  <v-chip size="small" :color="COLORS[status]" variant="tonal">
    {{ te(`status.${status}`) ? t(`status.${status}`) : status.toUpperCase() }}
  </v-chip>
</template>
