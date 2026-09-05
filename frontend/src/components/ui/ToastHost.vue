<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToastStore } from '../../stores/toast'

// Converted from the ui-example/alert.html mockup: the stacked back card, the
// filled circular icon with its glow, the accent bar down the leading edge and
// the title-over-message pairing are all kept. What changed is the palette —
// the mockup was a fixed dark-navy glass card, so its own colours are dropped
// in favour of the app's surface tokens (which flip with the theme) and the
// brand green, so a success reads as the same green as the sidebar.
const { t } = useI18n()
const toast = useToastStore()

// Every tone is pulled from a colour the app already uses rather than raw
// Tailwind reds and ambers: success is the brand itself, and the other three
// are the exact hexes behind .badge-danger / -warning / -info. That keeps the
// alerts quieter and of a piece with the badges in the tables behind them.
const TONES = {
  success: { bar: 'bg-brand',     dot: 'bg-brand text-white',     ring: 'ring-brand/15',     wash: 'from-brand/[0.07]' },
  error:   { bar: 'bg-[#a13b3b]', dot: 'bg-[#a13b3b] text-white', ring: 'ring-[#a13b3b]/15', wash: 'from-[#a13b3b]/[0.07]' },
  warning: { bar: 'bg-[#915a1a]', dot: 'bg-[#915a1a] text-white', ring: 'ring-[#915a1a]/15', wash: 'from-[#915a1a]/[0.07]' },
  info:    { bar: 'bg-[#2b5a8c]', dot: 'bg-[#2b5a8c] text-white', ring: 'ring-[#2b5a8c]/15', wash: 'from-[#2b5a8c]/[0.07]' },
}
const tone = (type) => TONES[type] || TONES.success

// A caller may pass its own heading; otherwise the type supplies one, which is
// what keeps every existing single-argument call rendering correctly.
function heading(item) {
  if (item.title) return item.title
  if (item.type === 'error') return t('toast.error')
  if (item.type === 'warning') return t('toast.warning')
  if (item.type === 'info') return t('toast.info')
  return t('toast.success')
}

// Which card the pointer is over. Held here rather than per-card so the
// countdown bar and the store's timer pause off the same signal.
const hovered = ref(null)

function hold(id) {
  hovered.value = id
  toast.pause(id)
}
function release(id) {
  if (hovered.value === id) hovered.value = null
  toast.resume(id)
}
</script>

<template>
  <!-- pointer-events-none on the stack so the empty column never swallows
       clicks on the page beneath it; each card re-enables them for itself. -->
  <div class="fixed top-4 right-4 left-4 sm:left-auto z-[200] w-auto sm:w-full sm:max-w-sm space-y-3.5 pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="item in toast.items"
        :key="item.id"
        class="relative isolate pointer-events-auto"
        @mouseenter="hold(item.id)"
        @mouseleave="release(item.id)"
      >
        <!-- Back layer: the mockup's offset "stacked cards" shadow, drawn as a
             real element rather than a box-shadow so it keeps the same rounded
             silhouette. -->
        <div class="absolute left-3 right-[-6px] top-2 bottom-[-6px] rounded-2xl bg-surface-2/70 border border-line/60 -z-10"></div>

        <div
          class="relative flex items-start gap-3.5 rounded-2xl border border-line bg-surface/95 backdrop-blur-xl
                 pl-5 pr-3 py-3.5 shadow-[var(--shadow-pop)] overflow-hidden
                 transition-transform duration-300 ease-out hover:-translate-x-1"
        >
          <!-- A whisper of the tone washed across the card, so the colour reads
               on the surface itself and not only on the bar and the icon. -->
          <span class="absolute inset-0 bg-gradient-to-r to-transparent pointer-events-none" :class="tone(item.type).wash"></span>

          <!-- Accent bar down the leading edge, the mockup's border-left. -->
          <span class="absolute left-0 inset-y-0 w-[3px]" :class="tone(item.type).bar"></span>

          <span
            class="relative flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center mt-0.5 ring-4"
            :class="[tone(item.type).dot, tone(item.type).ring]"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
              <path v-if="item.type === 'success'" fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" />
              <path v-else-if="item.type === 'error'" fill-rule="evenodd" clip-rule="evenodd" d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
              <path v-else-if="item.type === 'warning'" fill-rule="evenodd" clip-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" />
              <path v-else fill-rule="evenodd" clip-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" />
            </svg>
          </span>

          <div class="relative min-w-0 flex-1 pt-0.5">
            <p class="text-[15px] font-bold text-fg tracking-tight leading-tight">{{ heading(item) }}</p>
            <p class="text-[13px] text-muted leading-snug mt-1 break-words">{{ item.message }}</p>
          </div>

          <!-- Dismiss: the old host auto-cleared after 4s with no way to close
               a toast early. -->
          <button
            type="button"
            @click="toast.dismiss(item.id)"
            :title="t('common.close')"
            :aria-label="t('common.close')"
            class="relative flex-shrink-0 -mt-0.5 -mr-0.5 w-7 h-7 rounded-lg text-faint hover:text-fg hover:bg-surface-2
                   transition-colors duration-200 flex items-center justify-center
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/30"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
          </button>

          <!-- Countdown: how long is left before this clears. Driven by the
               same duration the store schedules on, and it halts with the timer
               while the pointer rests on the card. -->
          <span
            class="toast-progress absolute bottom-0 left-0 right-0 h-[2px] origin-left"
            :class="[tone(item.type).bar, hovered === item.id ? 'is-paused' : '']"
            :style="{ animationDuration: item.duration + 'ms' }"
          ></span>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
/* Enter on an expo-out curve so the card decelerates into place rather than
   stopping dead; leave is quicker and shorter, the way a dismissal should feel.
   `toast-move` is what keeps the rest of the stack gliding up into the gap. */
.toast-enter-active {
  transition: transform 0.42s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.28s ease-out;
}
.toast-leave-active {
  transition: transform 0.26s cubic-bezier(0.4, 0, 1, 1), opacity 0.2s ease-in;
  /* Taken out of flow so the cards below slide up smoothly instead of snapping
     the instant this one is removed. */
  position: absolute;
  left: 0;
  right: 0;
}
.toast-move {
  transition: transform 0.42s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(28px) scale(0.96);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(28px) scale(0.96);
}

/* scaleX rather than width: it runs on the compositor, so the bar stays smooth
   even while the page behind it is rendering a table. */
.toast-progress {
  animation-name: toast-progress;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
  opacity: 0.45;
}
.toast-progress.is-paused {
  animation-play-state: paused;
}
@keyframes toast-progress {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}

/* Anyone who has asked the OS for less motion gets a plain fade and a static
   bar instead of sliding cards. */
@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active,
  .toast-move {
    transition: opacity 0.15s linear;
  }
  .toast-enter-from,
  .toast-leave-to {
    transform: none;
  }
  .toast-progress {
    animation: none;
    transform: scaleX(1);
  }
}
</style>
