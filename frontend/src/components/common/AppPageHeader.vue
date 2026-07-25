<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  // [{ title, to?, href?, disabled? }]
  breadcrumbs: { type: Array, default: () => [] },
  // [{ label, icon?, color?, variant?, to?, onClick? }] — or use the #actions slot for full control.
  actions: { type: Array, default: () => [] },
})

defineEmits(['action'])
</script>

<template>
  <div class="d-flex flex-column flex-sm-row justify-space-between align-sm-center ga-4 mb-6">
    <div class="flex-grow-1" style="min-width: 0">
      <v-breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" class="pa-0 mb-1" density="compact" />
      <h1 class="text-h5 font-weight-bold text-truncate">{{ title }}</h1>
      <p v-if="subtitle" class="text-body-2 text-medium-emphasis mt-1 mb-0">{{ subtitle }}</p>
    </div>

    <div class="d-flex flex-wrap ga-2 flex-shrink-0">
      <slot name="actions">
        <v-btn
          v-for="(action, i) in actions"
          :key="action.label ?? i"
          :color="action.color ?? 'primary'"
          :variant="action.variant ?? 'flat'"
          :prepend-icon="action.icon"
          :to="action.to"
          @click="() => { action.onClick?.(); $emit('action', action) }"
        >
          {{ action.label }}
        </v-btn>
      </slot>
    </div>
  </div>
</template>
