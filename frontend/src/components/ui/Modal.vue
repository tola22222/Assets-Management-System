<script setup>
import { useI18n } from 'vue-i18n'

// The app's form-dialog shell, converted from the ui-example/dialog_form.html
// mockup and repainted in the PEPY tokens. What the mockup contributes: one
// 28px-gutter column with no rule between the header and the fields, a small
// neutral icon disc leading the title, an optional subtitle under it, and a
// footer whose actions sit right-aligned with the primary last.
//
// The page supplies the content, in one of two shapes:
//
//   <Modal :title="…">                    <Modal :title="…">
//     <form class="modal-form" …>           <div class="modal-body">…</div>
//       <div class="modal-body">…</div>    </Modal>
//       <div class="modal-footer">…</div>
//     </form>                              (read-only — the body carries the
//   </Modal>                                bottom gutter on its own)
//
// .modal-form/.modal-body are what keep a long form scrolling under a pinned
// footer; the mockup's own card is short enough that it never had to.
//
// `icon` is a slot rather than a prop so a page can pass whatever glyph fits
// its module; leave it out and the disc is dropped and the title leads.
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: null },
  wide: { type: Boolean, default: false },
  // Raises the overlay above another modal: the cropper opens on top of the
  // form that asked for it, and the shared z-index would put it behind.
  elevated: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])

const { t } = useI18n()
</script>

<template>
  <div class="overlay items-center justify-center" :class="elevated ? 'z-[160]' : ''" @click.self="emit('close')">
    <!-- 580px is the mockup's own card width; `wide` is the escape hatch for
         the few modals that carry a table or a permission matrix. -->
    <div class="modal-panel" :class="wide ? 'max-w-2xl' : 'max-w-[580px]'">
      <div class="modal-header">
        <span v-if="$slots.icon" class="modal-header-icon">
          <slot name="icon" />
        </span>
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
    </div>
  </div>
</template>
