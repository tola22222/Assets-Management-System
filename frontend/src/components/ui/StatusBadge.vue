<script setup>
defineProps({ status: { type: String, required: true } })

const classes = {
  approved: 'bg-emerald-100 text-emerald-700',
  active: 'bg-emerald-100 text-emerald-700',
  pending: 'bg-amber-100 text-amber-700',
  assigned: 'bg-blue-100 text-blue-700',
  rejected: 'bg-red-100 text-red-700',
  returned: 'bg-gray-200 text-gray-600',
}
</script>

<template>
  <span class="px-2.5 py-1 rounded-lg text-xs font-bold" :class="classes[status] || 'bg-gray-100 text-gray-600'">
    {{ status.toUpperCase() }}
  </span>
</template>
