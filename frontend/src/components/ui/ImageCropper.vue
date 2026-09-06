<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Modal from './Modal.vue'

// Fixed-aspect pan-and-zoom cropper — the shape every avatar/evidence cropper
// settles on: the frame stays put and the picture moves behind it. Free-form
// crop handles would need four drag targets and still hand back a photo the
// caller has to letterbox, and every consumer here wants one known aspect.
//
// It emits a real File (not a data URL), so the pages keep appending to
// FormData exactly as they did with the raw <input type="file">.
const props = defineProps({
  file: { type: File, required: true },
  aspect: { type: Number, default: 4 / 3 },
  // Cap on the long edge of the export. Phone cameras hand us 4000px JPEGs;
  // nothing in this app displays a photo above a few hundred px, and the upload
  // limit is what users actually hit.
  maxOutput: { type: Number, default: 1600 },
})
const emit = defineEmits(['apply', 'cancel'])

const { t } = useI18n()

const src = ref('')
const frame = ref(null)
const natural = ref({ w: 0, h: 0 })
const view = ref({ w: 0, h: 0 })
const zoom = ref(1)
const offset = ref({ x: 0, y: 0 })
const ready = ref(false)
const working = ref(false)

let imgEl = null
let ro = null

// "Cover" scale: the smallest scale at which the picture still fills the frame,
// and therefore the floor for zoom — panning can then never expose a gap.
const baseScale = computed(() => {
  if (!natural.value.w || !view.value.w) return 1
  return Math.max(view.value.w / natural.value.w, view.value.h / natural.value.h)
})
const scale = computed(() => baseScale.value * zoom.value)
const drawn = computed(() => ({
  w: natural.value.w * scale.value,
  h: natural.value.h * scale.value,
}))

function clamp() {
  const minX = view.value.w - drawn.value.w
  const minY = view.value.h - drawn.value.h
  offset.value = {
    x: Math.min(0, Math.max(minX, offset.value.x)),
    y: Math.min(0, Math.max(minY, offset.value.y)),
  }
}

function centre() {
  offset.value = {
    x: (view.value.w - drawn.value.w) / 2,
    y: (view.value.h - drawn.value.h) / 2,
  }
  clamp()
}

function measure() {
  if (!frame.value) return
  const r = frame.value.getBoundingClientRect()
  view.value = { w: r.width, h: r.height }
  clamp()
}

// Zooming pins the frame's centre, so whatever is being looked at doesn't slide
// out from under the pointer as the slider moves.
watch(zoom, (next, prev) => {
  if (!prev || !view.value.w) return
  const ratio = next / prev
  const cx = view.value.w / 2
  const cy = view.value.h / 2
  offset.value = {
    x: cx - (cx - offset.value.x) * ratio,
    y: cy - (cy - offset.value.y) * ratio,
  }
  clamp()
})

let dragging = false
let last = { x: 0, y: 0 }

function onPointerDown(e) {
  if (!ready.value) return
  dragging = true
  last = { x: e.clientX, y: e.clientY }
  e.currentTarget.setPointerCapture(e.pointerId)
}
function onPointerMove(e) {
  if (!dragging) return
  offset.value = {
    x: offset.value.x + (e.clientX - last.x),
    y: offset.value.y + (e.clientY - last.y),
  }
  last = { x: e.clientX, y: e.clientY }
  clamp()
}
function onPointerUp(e) {
  dragging = false
  if (e.currentTarget.hasPointerCapture && e.currentTarget.hasPointerCapture(e.pointerId)) {
    e.currentTarget.releasePointerCapture(e.pointerId)
  }
}
function onWheel(e) {
  if (!ready.value) return
  e.preventDefault()
  zoom.value = Math.min(4, Math.max(1, zoom.value - e.deltaY * 0.0015))
}

function reset() {
  zoom.value = 1
  centre()
}

// PNG in, PNG out: re-encoding a transparent source as JPEG paints the
// transparency black. Everything else becomes a JPEG, which is what keeps a
// phone photo under the upload limit.
function outputType() {
  return props.file.type === 'image/png' ? 'image/png' : 'image/jpeg'
}
function outputName() {
  const base = props.file.name.replace(/\.[^.]+$/, '') || 'photo'
  return base + (outputType() === 'image/png' ? '.png' : '.jpg')
}

function apply() {
  if (!ready.value || working.value) return
  working.value = true

  // Source rectangle in the image's own pixels: undo the display scale and the
  // pan, so the export is at the picture's real resolution rather than at
  // whatever size the frame happened to be on screen.
  const sx = -offset.value.x / scale.value
  const sy = -offset.value.y / scale.value
  const sw = view.value.w / scale.value
  const sh = view.value.h / scale.value

  let ow = sw
  let oh = sh
  if (Math.max(ow, oh) > props.maxOutput) {
    const k = props.maxOutput / Math.max(ow, oh)
    ow *= k
    oh *= k
  }

  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(ow))
  canvas.height = Math.max(1, Math.round(oh))
  const ctx = canvas.getContext('2d')
  ctx.imageSmoothingQuality = 'high'
  ctx.drawImage(imgEl, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height)

  canvas.toBlob(
    (blob) => {
      working.value = false
      if (!blob) return
      emit('apply', new File([blob], outputName(), { type: outputType() }))
    },
    outputType(),
    0.9
  )
}

onMounted(() => {
  src.value = URL.createObjectURL(props.file)
  imgEl = new Image()
  imgEl.onload = () => {
    natural.value = { w: imgEl.naturalWidth, h: imgEl.naturalHeight }
    measure()
    centre()
    ready.value = true
  }
  imgEl.src = src.value

  // The frame is fluid, so a resize — or the modal settling on its final width
  // a frame after mount — has to re-measure, or the export maths runs against a
  // stale viewport and the crop lands somewhere else.
  if (window.ResizeObserver && frame.value) {
    ro = new ResizeObserver(() => measure())
    ro.observe(frame.value)
  }
})

onBeforeUnmount(() => {
  if (ro) ro.disconnect()
  if (src.value) URL.revokeObjectURL(src.value)
})
</script>

<template>
  <Modal
    elevated
    :title="t('image.crop_title')"
    :subtitle="t('image.crop_subtitle')"
    @close="emit('cancel')"
  >
    <template #icon>
      <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 2v14a2 2 0 0 0 2 2h14" />
        <path d="M18 22V8a2 2 0 0 0-2-2H2" />
      </svg>
    </template>

    <div class="modal-body space-y-4">
      <div
        ref="frame"
        class="crop-frame"
        :style="{ aspectRatio: String(aspect), maxWidth: 'min(100%, calc(52vh * ' + aspect + '))' }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
        @wheel="onWheel"
      >
        <img
          v-if="src"
          :src="src"
          alt=""
          draggable="false"
          class="crop-image"
          :style="{
            width: drawn.w + 'px',
            height: drawn.h + 'px',
            transform: 'translate3d(' + offset.x + 'px,' + offset.y + 'px,0)',
          }"
        />
        <!-- Rule-of-thirds guides, drawn over the picture rather than around it
             so the frame edge stays the crop edge. -->
        <span class="crop-guides" aria-hidden="true"></span>
      </div>

      <div class="flex items-center gap-3">
        <span class="text-xs font-semibold text-muted flex-shrink-0">{{ t('image.zoom') }}</span>
        <input
          v-model.number="zoom"
          type="range"
          min="1"
          max="4"
          step="0.01"
          class="crop-range"
          :aria-label="t('image.zoom')"
        />
        <button type="button" class="btn-ghost btn-sm flex-shrink-0" @click="reset">
          {{ t('image.reset') }}
        </button>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn-ghost" @click="emit('cancel')">{{ t('common.cancel') }}</button>
      <button type="button" class="btn-primary" :disabled="!ready || working" @click="apply">
        {{ t('image.apply') }}
      </button>
    </div>
  </Modal>
</template>
