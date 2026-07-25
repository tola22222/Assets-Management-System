<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

defineProps({
  align: { type: String, default: 'right' }, // 'left' | 'right'
})

const open = ref(false)
const root = ref(null)

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function onClickOutside(e) {
  if (root.value && !root.value.contains(e.target)) close()
}

function onKeydown(e) {
  if (e.key === 'Escape') close()
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
  document.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  document.removeEventListener('keydown', onKeydown)
})

defineExpose({ close })
</script>

<template>
  <div ref="root" class="relative inline-block">
    <span @click="toggle">
      <slot name="trigger" :open="open" />
    </span>
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open"
        class="absolute z-30 mt-1.5 min-w-[180px] py-1.5 bg-surface border border-line rounded-xl shadow-[var(--shadow-pop)]"
        :class="align === 'right' ? 'right-0' : 'left-0'"
        @click="close"
      >
        <slot />
      </div>
    </Transition>
  </div>
</template>
