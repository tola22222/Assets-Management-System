<script setup>
import { useI18n } from 'vue-i18n'

const { t, te } = useI18n()
defineProps({ status: { type: String, required: true } })

const classes = {
  approved: 'badge-success',
  active: 'badge-success',
  completed: 'badge-success',
  pending: 'badge-warning',
  assigned: 'badge-info',
  rejected: 'badge-danger',
  disposed: 'badge-danger',
  returned: 'badge-neutral',
  stock_in: 'badge-success',
  transfer: 'badge-info',
  overdue: 'badge-danger',
}
</script>

<template>
  <span :class="classes[status] || 'badge-neutral'">
    {{ te(`status.${status}`) ? t(`status.${status}`) : status.toUpperCase() }}
  </span>
</template>
