<script setup>
import { useI18n } from 'vue-i18n'

const { t, te } = useI18n()
defineProps({ status: { type: String, required: true } })

const classes = {
  approved: 'badge-success',
  active: 'badge-success',
  completed: 'badge-success',
  received: 'badge-success',
  // Sitting with the destination site, waiting to be accepted or rejected —
  // the asset is still recorded at the sending site.
  pending: 'badge-warning',
  // Waiting on OPM to release it; it has not reached the destination yet.
  pending_approval: 'badge-warning',
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
