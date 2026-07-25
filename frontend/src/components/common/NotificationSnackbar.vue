<script setup>
import { computed } from 'vue'
import { useNotificationStore } from '../../stores/notification'

const store = useNotificationStore()

// Show one at a time; the next queued notification appears once this one is dismissed.
const current = computed(() => store.queue[0] ?? null)

const COLOR = { success: 'success', error: 'error', warning: 'warning', info: 'info' }
const ICON = {
  success: 'mdi-check-circle',
  error: 'mdi-alert-circle',
  warning: 'mdi-alert',
  info: 'mdi-information',
}

function close() {
  if (current.value) store.dismiss(current.value.id)
}

function runAction() {
  current.value?.action?.onClick?.()
  close()
}
</script>

<template>
  <v-snackbar
    :model-value="!!current"
    :color="current ? COLOR[current.type] : undefined"
    :timeout="current?.timeout ?? 4000"
    location="top right"
    variant="elevated"
    @update:model-value="(v) => !v && close()"
  >
    <div class="d-flex align-center ga-2">
      <v-icon :icon="current ? ICON[current.type] : ''" />
      <span>{{ current?.message }}</span>
    </div>

    <template #actions>
      <v-btn v-if="current?.action" variant="text" @click="runAction">
        {{ current.action.label }}
      </v-btn>
      <v-btn icon="mdi-close" variant="text" density="comfortable" @click="close" />
    </template>
  </v-snackbar>
</template>
