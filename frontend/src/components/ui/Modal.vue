<script setup>
defineProps({
  title: { type: String, required: true },
  wide: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])
</script>

<template>
  <v-dialog
    :model-value="true"
    :max-width="wide ? 720 : 520"
    scrollable
    @update:model-value="(v) => !v && emit('close')"
  >
    <v-card rounded="lg">
      <v-card-title class="d-flex align-center justify-space-between pa-4">
        <span class="text-h6 font-weight-bold">{{ title }}</span>
        <v-btn icon="mdi-close" variant="text" size="small" @click="emit('close')" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <slot />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>
