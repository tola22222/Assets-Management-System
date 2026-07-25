<script setup>
import { useConfirmStore } from '../../stores/confirm'

const store = useConfirmStore()
</script>

<template>
  <v-dialog
    :model-value="store.visible"
    max-width="440"
    persistent
    @update:model-value="(v) => !v && store.cancel()"
  >
    <v-card :prepend-icon="store.options.icon" rounded="lg">
      <template #title>{{ store.options.title }}</template>

      <v-card-text v-if="store.options.message" class="text-body-2">
        {{ store.options.message }}
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="store.cancel">{{ store.options.cancelText }}</v-btn>
        <v-btn :color="store.options.color" variant="flat" @click="store.confirm">
          {{ store.options.confirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
