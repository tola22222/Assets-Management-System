<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
// `tone` picks the icon and confirm-button colour: 'danger' (the default) for
// the destructive confirms this dialog was written for, 'primary' for a plain
// "are you sure?" — an apply/save step drawn in red with a Delete button reads
// as data loss. Always pass `confirmLabel` for a non-delete action: the button
// falls back to common.delete.
const props = defineProps({
  title: { type: String, default: null },
  message: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  tone: { type: String, default: 'danger' },
})
const emit = defineEmits(['confirm', 'cancel'])

const isDanger = computed(() => props.tone !== 'primary')

// Same palette as the toasts: danger is the hex behind .badge-danger rather
// than a raw Tailwind red, so a confirm and the error toast that may follow it
// are the same colour.
const skin = computed(() =>
  isDanger.value
    ? { bar: 'bg-[#a13b3b]', dot: 'bg-[#a13b3b] text-white', ring: 'ring-[#a13b3b]/15', wash: 'from-[#a13b3b]/[0.07]', btn: 'bg-[#a13b3b] hover:bg-[#8c3232] text-white focus-visible:ring-[#a13b3b]/25' }
    : { bar: 'bg-brand', dot: 'bg-brand text-white', ring: 'ring-brand/15', wash: 'from-brand/[0.07]', btn: 'bg-brand hover:bg-brand-dark text-white focus-visible:ring-brand/25' }
)

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
  <!-- Backdrop fades on its own timing so the blur builds up behind the panel
       rather than snapping in with it. -->
  <div
    class="overlay items-center justify-center z-[150] transition-opacity duration-200 ease-out"
    :class="visible ? 'opacity-100' : 'opacity-0'"
    role="dialog"
    aria-modal="true"
    @click.self="close('cancel')"
  >
    <div
      class="relative isolate w-full max-w-sm transition-all duration-300"
      :class="visible ? 'opacity-100 scale-100 translate-y-0' : 'opacity-0 scale-95 translate-y-2'"
      style="transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1)"
    >
      <!-- The alert mockup's offset back card, kept so the popup shares the
           toasts' stacked-paper look. -->
      <div class="absolute left-4 right-[-7px] top-3 bottom-[-7px] rounded-2xl bg-surface-2/60 border border-line/60 -z-10"></div>

      <div class="relative modal-panel max-w-none p-0 overflow-hidden">
        <!-- Tone bar across the top: a centred dialog reads better with the
             accent above the icon than down one edge. -->
        <span class="absolute top-0 inset-x-0 h-[3px]" :class="skin.bar"></span>
        <span class="absolute inset-0 bg-gradient-to-b to-transparent pointer-events-none" :class="skin.wash"></span>

        <div class="relative p-6 text-center">
          <div
            class="w-14 h-14 mx-auto rounded-full flex items-center justify-center ring-8"
            :class="[skin.dot, skin.ring]"
          >
            <svg v-if="isDanger" class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" /></svg>
            <svg v-else class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm11.378-3.917c-.89-.777-2.366-.777-3.255 0a.75.75 0 01-.988-1.129c1.454-1.272 3.776-1.272 5.23 0 1.513 1.324 1.513 3.518 0 4.842a3.75 3.75 0 01-.837.552c-.676.328-1.028.774-1.028 1.152v.75a.75.75 0 01-1.5 0v-.75c0-1.279 1.06-2.107 1.875-2.502.182-.088.351-.199.503-.331.83-.727.83-1.857 0-2.584zM12 18a.75.75 0 100-1.5.75.75 0 000 1.5z" /></svg>
          </div>

          <h3 class="text-lg font-bold text-fg tracking-tight mt-4">{{ title ?? t('confirm.delete_title') }}</h3>
          <p class="text-sm text-muted leading-relaxed mt-2">{{ message ?? t('confirm.delete_message') }}</p>
        </div>

        <!-- Actions sit on their own footer band, divided from the message, so
             the destructive button reads as a deliberate step rather than part
             of the copy. Stacked on a phone with Cancel underneath. -->
        <div class="relative flex flex-col-reverse sm:flex-row sm:justify-center gap-2.5 px-6 pb-6 pt-1">
          <button
            ref="cancelButton"
            type="button"
            @click="close('cancel')"
            class="btn-ghost sm:px-7"
          >{{ t('common.cancel') }}</button>
          <button
            type="button"
            @click="close('confirm')"
            class="inline-flex items-center justify-center gap-2 font-semibold text-sm px-4 sm:px-7 py-2.5 rounded-xl
                   shadow-[var(--shadow-card)] transition-colors duration-150
                   focus:outline-none focus-visible:ring-4"
            :class="skin.btn"
          >{{ confirmLabel ?? t('common.delete') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
