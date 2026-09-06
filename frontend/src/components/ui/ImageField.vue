<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ImageCropper from './ImageCropper.vue'

// Photo field for the dialog forms: the dialog_form.html dropzone as the empty
// state, drag-and-drop or click to pick, then the cropper before anything is
// handed back. It models a File, which is what every caller already appended to
// its FormData — so it drops in where a bare <input type="file"> used to sit.
//
// The picked original is kept alongside the cropped result so re-cropping
// starts from the full picture instead of re-cropping a crop.
const props = defineProps({
  modelValue: { type: File, default: null },
  aspect: { type: Number, default: 4 / 3 },
  maxMb: { type: Number, default: 5 },
  accept: { type: String, default: 'image/jpeg,image/png' },
  // URL of the photo already on the record, so an edit dialog shows what is
  // there now rather than an empty well that implies there is no photo.
  existing: { type: String, default: null },
  // The mockup pairs the field label with a quieter second line above the well
  // ("Drag and drop document to upload your support task"). The label itself
  // stays with the page, since only the page knows what the photo is for.
  hint: { type: String, default: null },
})
const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const input = ref(null)
const dragging = ref(0)
const error = ref('')
const pending = ref(null)
const original = ref(null)
const previewUrl = ref('')

const hasFile = computed(() => !!props.modelValue)
const showExisting = computed(() => !hasFile.value && !!props.existing)

// One object URL at a time, revoked as it is replaced — a form that is opened
// and cancelled a dozen times otherwise leaks a blob per attempt.
watch(
  () => props.modelValue,
  (file) => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = file ? URL.createObjectURL(file) : ''
    if (!file) original.value = null
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})

function accepts(file) {
  const allowed = props.accept.split(',').map((s) => s.trim()).filter(Boolean)
  return allowed.length === 0 || allowed.includes(file.type)
}

function take(file) {
  error.value = ''
  if (!file) return
  if (!accepts(file)) {
    error.value = t('image.invalid_type')
    return
  }
  if (file.size > props.maxMb * 1024 * 1024) {
    error.value = t('image.too_large', { size: props.maxMb })
    return
  }
  original.value = file
  pending.value = file
}

function onPick(e) {
  take(e.target.files[0] || null)
  // Reset the control, or picking the same file twice in a row fires no change
  // event and the cropper never opens the second time.
  e.target.value = ''
}

function onDrop(e) {
  dragging.value = 0
  take(e.dataTransfer?.files?.[0] || null)
}

// dragenter/dragleave fire for every child element the pointer crosses, so a
// boolean flickers as the cursor moves over the icon or the button. Counting
// enters against leaves is what keeps the highlight steady.
function onDragEnter() {
  dragging.value += 1
}
function onDragLeave() {
  dragging.value = Math.max(0, dragging.value - 1)
}

function browse() {
  input.value?.click()
}

function onCropped(file) {
  pending.value = null
  emit('update:modelValue', file)
}

function recrop() {
  if (original.value) pending.value = original.value
}

function remove() {
  error.value = ''
  original.value = null
  emit('update:modelValue', null)
}

function sizeLabel(file) {
  const kb = file.size / 1024
  return kb < 1024 ? Math.round(kb) + ' KB' : (kb / 1024).toFixed(1) + ' MB'
}
</script>

<template>
  <div>
    <input
      ref="input"
      type="file"
      :accept="accept"
      class="hidden"
      @change="onPick"
    />

    <span v-if="hint && !hasFile && !showExisting" class="label-sub">{{ hint }}</span>

    <!-- Empty: the mockup's dashed well, which doubles as the drop target. -->
    <div
      v-if="!hasFile && !showExisting"
      class="dropzone"
      :class="dragging > 0 ? 'dropzone-active' : ''"
      role="button"
      tabindex="0"
      @click="browse"
      @keydown.enter.prevent="browse"
      @keydown.space.prevent="browse"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <div class="dropzone-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17.5 19a4.5 4.5 0 0 0 .39-8.98A7.1 7.1 0 0 0 4.5 8.53 5.4 5.4 0 0 0 5.4 19h12.1z" />
          <polyline points="12 16 12 11" />
          <polyline points="9 13 12 10.5 15 13" />
        </svg>
      </div>
      <span class="dropzone-title">{{ t('image.drop_title') }}</span>
      <span class="dropzone-hint">{{ t('image.drop_hint', { size: maxMb }) }}</span>
      <button type="button" class="btn-browse" @click.stop="browse">{{ t('image.browse') }}</button>
    </div>

    <!-- Chosen, or already on the record: show it rather than a filename. -->
    <div v-else class="image-preview">
      <div class="image-preview-thumb" :style="{ aspectRatio: String(aspect) }">
        <img :src="hasFile ? previewUrl : existing" alt="" />
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-[13px] font-semibold text-fg truncate">
          {{ hasFile ? modelValue.name : t('image.current_photo') }}
        </p>
        <p class="text-xs text-muted mt-0.5">
          {{ hasFile ? sizeLabel(modelValue) : t('image.replace_hint') }}
        </p>
        <div class="flex flex-wrap gap-2 mt-2.5">
          <button v-if="hasFile && original" type="button" class="btn-ghost btn-sm" @click="recrop">
            {{ t('image.recrop') }}
          </button>
          <button type="button" class="btn-ghost btn-sm" @click="browse">
            {{ hasFile ? t('image.change') : t('image.replace') }}
          </button>
          <button v-if="hasFile" type="button" class="btn-ghost btn-sm" @click="remove">
            {{ t('image.remove') }}
          </button>
        </div>
      </div>
    </div>

    <p v-if="error" class="text-xs text-red-600 dark:text-red-400 mt-1.5">{{ error }}</p>

    <ImageCropper
      v-if="pending"
      :file="pending"
      :aspect="aspect"
      @apply="onCropped"
      @cancel="pending = null"
    />
  </div>
</template>
