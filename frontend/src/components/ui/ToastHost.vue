<script setup>
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
</script>

<template>
  <div class="fixed top-4 right-4 z-[200] space-y-2 w-full max-w-sm">
    <TransitionGroup name="toast">
      <div v-for="t in toast.items" :key="t.id"
        class="px-4 py-3 rounded-xl text-sm shadow-lg border"
        :class="t.type === 'error'
          ? 'bg-red-50 border-red-200 text-red-700'
          : 'bg-emerald-50 border-emerald-200 text-emerald-700'">
        {{ t.message }}
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.2s ease; }
.toast-enter-from { opacity: 0; transform: translateY(-8px); }
.toast-leave-to { opacity: 0; transform: translateX(20px); }
</style>
