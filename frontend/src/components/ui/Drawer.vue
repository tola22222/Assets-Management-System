<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'

// The dialog as a drawer: the same header, body and footer as Modal.vue (and
// the same .modal-form / .modal-body / .modal-footer content shape), but the
// panel slides in from the right at full height. For long forms — the role
// editor's permission grid — where a centred card would scroll in a box.
//
//   <Drawer :title="…" @close="…">
//     <form class="modal-form" …>
//       <div class="modal-body">…</div>
//       <div class="modal-footer">…</div>
//     </form>
//   </Drawer>
//
// Escape and a click on the scrim close it, like Modal.
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: null },
})
const emit = defineEmits(['close'])

const { t } = useI18n()

function onKey(e) {
  if (e.key === 'Escape') emit('close')
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="overlay !p-0 justify-end" @click.self="emit('close')">
    <aside class="drawer-panel" role="dialog" aria-modal="true" :aria-label="title">
      <div class="modal-header">
        <div class="min-w-0 flex-1">
          <h3 class="modal-title">{{ title }}</h3>
          <p v-if="subtitle" class="modal-subtitle">{{ subtitle }}</p>
        </div>
        <button
          type="button"
          class="modal-close"
          :title="t('common.close')"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <slot />
    </aside>
  </div>
</template>
