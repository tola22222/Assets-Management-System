<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
// Drawn from the ui-example/alert.html mockup: a small centred card with a soft
// tinted icon disc, the title over a short line of copy, and two equal pill
// actions. The mockup's blue is swapped for the app's own palette — brand green
// for a plain confirm, the .badge-danger red for a destructive one — so the
// dialog matches the page it opens over.
//
// `tone` picks the icon disc and confirm-button colour: 'danger' (the default)
// for the destructive confirms this dialog was written for, 'primary' for a
// plain "are you sure?" — an apply/save step drawn in red with a Delete button
// reads as data loss. Always pass `confirmLabel` for a non-delete action: the
// button falls back to common.delete.
//
// `icon` overrides the glyph. The default follows the tone (a trash can for
// danger, since nearly every caller is a delete; a question mark otherwise),
// so pass 'alert' for a destructive-but-not-deleting step like a restore.
const props = defineProps({
  title: { type: String, default: null },
  message: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  tone: { type: String, default: 'danger' },
  icon: { type: String, default: null },
})
const emit = defineEmits(['confirm', 'cancel'])

const isDanger = computed(() => props.tone !== 'primary')

// The disc tint and the confirm fill are deliberately the same hue, the way the
// mockup pairs its blue icon wash with its blue button. The light tints are the
// .badge-danger / brand-100 washes; under dark they would glare, so each drops to
// a translucent version of the same colour over the dark surface.
const skin = computed(() =>
  isDanger.value
    ? {
        disc: 'bg-[#fcebeb] text-[#a13b3b] dark:bg-[#a13b3b]/20 dark:text-[#e0a1a1]',
        confirm: 'btn-pill-danger',
      }
    : {
        disc: 'bg-brand-100 text-brand dark:bg-brand-300/20 dark:text-brand-100',
        confirm: 'btn-pill-primary',
      }
)

const glyph = computed(() => props.icon ?? (isDanger.value ? 'trash' : 'question'))

// Every caller mounts this behind its own `v-if`, so an exit transition would
// normally be impossible — the component is torn down the instant the event
// fires. Holding the visible state internally and delaying the emit lets the
// panel animate out first, with no change at any of the call sites.
const visible = ref(false)
const cancelButton = ref(null)
let closing = false

function close(event) {
  if (closing) return
  closing = true
  visible.value = false
  setTimeout(() => emit(event), 160)
}

function onKeydown(e) {
  if (e.key === 'Escape') close('cancel')
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  requestAnimationFrame(() => {
    visible.value = true
    // Focus lands on Cancel, not on the destructive button: a stray Enter
    // should back out of a delete, not commit it. It has to happen after the
    // panel is in the DOM, and in the same frame the transition starts —
    // queueing it as a second rAF ran before the ref had settled.
    cancelButton.value?.focus()
  })
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <!-- Backdrop fades on its own timing so the blur builds up behind the card
       rather than snapping in with it. -->
  <div
    class="overlay items-center justify-center z-[150] transition-opacity duration-200 ease-out"
    :class="visible ? 'opacity-100' : 'opacity-0'"
    role="dialog"
    aria-modal="true"
    @click.self="close('cancel')"
  >
    <div
      class="dialog-card transition-all duration-300"
      :class="visible ? 'opacity-100 scale-100 translate-y-0' : 'opacity-0 scale-95 translate-y-2'"
      style="transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1)"
    >
      <!-- Line icons at the mockup's 24px/stroke-2, not the filled glyphs used
           in the toasts: at 24px inside a pale disc an outline reads as a quiet
           label for the dialog rather than a second warning. -->
      <div class="dialog-icon" :class="skin.disc">
        <svg
          class="w-6 h-6"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <template v-if="glyph === 'trash'">
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            <line x1="10" y1="11" x2="10" y2="17" />
            <line x1="14" y1="11" x2="14" y2="17" />
          </template>
          <template v-else-if="glyph === 'alert'">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </template>
          <template v-else>
            <circle cx="12" cy="12" r="10" />
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </template>
        </svg>
      </div>

      <h3 class="dialog-title">{{ title ?? t('confirm.delete_title') }}</h3>
      <p class="dialog-text">{{ message ?? t('confirm.delete_message') }}</p>

      <!-- Two equal pills, cancel first: the way out sits under the thumb on a
           phone and reads first left-to-right, and only the confirm is filled. -->
      <div class="dialog-actions">
        <button
          ref="cancelButton"
          type="button"
          class="btn-pill-neutral"
          @click="close('cancel')"
        >{{ t('common.cancel') }}</button>
        <button
          type="button"
          :class="skin.confirm"
          @click="close('confirm')"
        >{{ confirmLabel ?? t('common.delete') }}</button>
      </div>
    </div>
  </div>
</template>
