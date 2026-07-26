<script setup>
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
</script>

<template>
  <div class="toast-host">
    <TransitionGroup name="toast">
      <v-alert
        v-for="t in toast.items"
        :key="t.id"
        :type="['error', 'info', 'warning'].includes(t.type) ? t.type : 'success'"
        variant="tonal"
        density="compact"
        border="start"
        class="mb-2"
      >
        {{ t.message }}
      </v-alert>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-host {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 2000;
  width: 100%;
  max-width: 384px;
}
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
