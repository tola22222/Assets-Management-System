<script setup>
import { ref, computed, onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import Modal from '../ui/Modal.vue'
import ImageCropper from '../ui/ImageCropper.vue'
import { useToastStore } from '../../stores/toast'

// Bulk import of the asset register, as a form dialog like every other
// create/edit form (Modal: header, scrolling body, footer actions). Opened
// from Add Asset → Import. Three steps: the file, optional photos, the result.
// Emits `imported` once rows were saved (the page reloads its list) and
// `close` when dismissed.
const emit = defineEmits(['close', 'imported'])
const { t } = useI18n()
const toast = useToastStore()

const file = ref(null)
const fileInput = ref(null)
const generateQr = ref(true)
const importing = ref(false)
const dragging = ref(false)
const result = ref(null)

// Optional bulk photo attach: each image is matched server-side to a row by
// filename (asset code or serial number), so no extra spreadsheet column is needed.
// Each entry is { id, file, original, url }: the cropped file that is uploaded,
// the picture it was cropped from (so Crop again starts from the full photo,
// as in the photo box), and a preview URL.
const images = ref([])
const imagesInput = ref(null)
const changeInput = ref(null)
const imagesDragging = ref(false)

// The stepper is not a gate — every step up to the furthest reached one stays clickable.
const steps = [
  { id: 'file', label: 'import.step_file' },
  { id: 'photos', label: 'import.step_photos' },
  { id: 'complete', label: 'import.step_complete' },
]
const current = ref(0)
// Photos only picks photos; the import itself is started from step 3, which
// is reachable once a file is chosen.
const maxStep = computed(() => (result.value || file.value ? 2 : 1))
function goTo(i) {
  if (i <= maxStep.value) current.value = i
}

// The server reads the chosen file the same way the import does, and nothing
// is saved: Total assets on step 3 (a quantity column counts that many units)
// and the photo groups on step 2. Read once per file.
const preview = ref(null) // { rows, assets, groups: [{ key, category, name, count }] }
const previewing = ref(false)
const previewError = ref('')
let previewFor = null

async function loadPreview() {
  const f = file.value
  if (!f || previewFor === f) return
  previewFor = f
  preview.value = null
  previewError.value = ''
  previewing.value = true
  try {
    const fd = new FormData()
    fd.append('file', f)
    const { data } = await http.post('/assets/import/preview', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    if (file.value === f) {
      preview.value = data
      // A different file: keep only the photos whose group it still has.
      const keys = new Set((data.groups || []).map((g) => g.key))
      Object.keys(groupPhotos.value).filter((k) => !keys.has(k)).forEach(removeGroupPhoto)
    }
  } catch (e) {
    if (file.value === f) {
      previewError.value = errorMessage(e, t('import.failed'))
      previewFor = null
    }
  } finally {
    if (file.value === f) previewing.value = false
  }
}
watch(current, (step) => {
  if (step >= 1 && !result.value) loadPreview()
})

// One photo per asset group — category + asset name, e.g. COM · Dell (25) and
// COM · Asus (20) — put on every asset of that group. A photo named after one
// asset code (the list below) still wins for that asset. Keyed by group key:
// { file, original, url }, like the photo list.
const groups = computed(() => preview.value?.groups || [])
const groupPhotos = ref({})
const groupInput = ref(null)
let groupTarget = null

function chooseGroupImage(key) {
  groupTarget = key
  groupInput.value?.click()
}
function onGroupPicked(e) {
  const picked = e.target.files[0]
  e.target.value = ''
  if (picked && groupTarget) queueForCrop([picked], null, groupTarget)
  groupTarget = null
}
function recropGroup(key) {
  const photo = groupPhotos.value[key]
  if (photo) queueForCrop([photo.original], null, key)
}
function removeGroupPhoto(key) {
  const photo = groupPhotos.value[key]
  if (!photo) return
  URL.revokeObjectURL(photo.url)
  const next = { ...groupPhotos.value }
  delete next[key]
  groupPhotos.value = next
}
function clearGroupPhotos() {
  Object.keys(groupPhotos.value).forEach(removeGroupPhoto)
}
onBeforeUnmount(clearGroupPhotos)

// Photos going up with the import, for the step 3 summary.
const photoCount = computed(() => images.value.length + Object.keys(groupPhotos.value).length)

function pick(e) {
  file.value = e.target.files[0] || null
  result.value = null
}
function onDrop(e) {
  dragging.value = false
  file.value = e.dataTransfer.files[0] || null
  result.value = null
}

// Every photo goes through the same Crop photo dialog as the other uploads
// (4:3, like Add Asset), one after another when several are picked at once.
// The cropper keeps the file's name, so the asset-code matching above still
// works. Cancel skips that photo, as cancelling a crop does everywhere else.
// A job with replaceId is Crop again / Change on a listed photo: it replaces
// that photo in place, and cancelling it leaves the photo as it was. A job
// with groupKey is the photo for that asset group, likewise.
const cropQueue = ref([])
let seq = 0
const cropping = computed(() => cropQueue.value[0] || null)

function queueForCrop(files, replaceId = null, groupKey = null) {
  cropQueue.value = [...cropQueue.value, ...files.map((f) => ({ id: ++seq, file: f, replaceId, groupKey }))]
  if (files.length) result.value = null
}
function onPhotoCropped(cropped) {
  const job = cropQueue.value[0]
  cropQueue.value = cropQueue.value.slice(1)
  const url = URL.createObjectURL(cropped)
  if (job.groupKey) {
    const old = groupPhotos.value[job.groupKey]
    if (old) URL.revokeObjectURL(old.url)
    groupPhotos.value = { ...groupPhotos.value, [job.groupKey]: { file: cropped, original: job.file, url } }
    return
  }
  if (job.replaceId) {
    images.value = images.value.map((img) => {
      if (img.id !== job.replaceId) return img
      URL.revokeObjectURL(img.url)
      return { ...img, file: cropped, original: job.file, url }
    })
    return
  }
  images.value = [...images.value, { id: ++seq, file: cropped, original: job.file, url }]
}
function skipPhoto() {
  cropQueue.value = cropQueue.value.slice(1)
}

function pickImages(e) {
  queueForCrop(Array.from(e.target.files || []))
  e.target.value = ''
}
function onDropImages(e) {
  imagesDragging.value = false
  queueForCrop(Array.from(e.dataTransfer.files || []).filter((f) => f.type.startsWith('image/')))
}

function recropImage(img) {
  queueForCrop([img.original], img.id)
}
// Change: pick one new picture for this entry, cropped like the rest.
let changeTarget = null
function changeImage(img) {
  changeTarget = img.id
  changeInput.value?.click()
}
function onChangePicked(e) {
  const picked = e.target.files[0]
  e.target.value = ''
  if (picked && changeTarget) queueForCrop([picked], changeTarget)
  changeTarget = null
}
function removeImage(id) {
  const img = images.value.find((i) => i.id === id)
  if (img) URL.revokeObjectURL(img.url)
  images.value = images.value.filter((i) => i.id !== id)
}
function clearImages() {
  images.value.forEach((img) => URL.revokeObjectURL(img.url))
  images.value = []
}
onBeforeUnmount(clearImages)

function sizeLabel(f) {
  const kb = f.size / 1024
  return kb < 1024 ? Math.round(kb) + ' KB' : (kb / 1024).toFixed(1) + ' MB'
}

async function submit() {
  if (!file.value) return
  importing.value = true
  result.value = null
  try {
    const fd = new FormData()
    fd.append('file', file.value)
    fd.append('generate_qr', generateQr.value ? '1' : '0')
    images.value.forEach((img) => fd.append('images[]', img.file))
    // group_images[i] is the photo for the group whose key is group_keys[i].
    groups.value.forEach((g) => {
      const photo = groupPhotos.value[g.key]
      if (!photo) return
      fd.append('group_keys[]', g.key)
      fd.append('group_images[]', photo.file)
    })
    const { data } = await http.post('/assets/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    result.value = data
    current.value = 2
    toast.success(t('import.success_message', { created: data.created, updated: data.updated }))
    emit('imported')
  } catch (e) {
    toast.error(errorMessage(e, t('import.failed')))
  } finally {
    importing.value = false
  }
}

async function downloadTemplate() {
  try {
    const { data } = await http.get('/assets/import/template', { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = 'asset_import_template.csv'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    toast.error(errorMessage(e, t('import.template_failed')))
  }
}

function reset() {
  file.value = null
  clearImages()
  clearGroupPhotos()
  cropQueue.value = []
  preview.value = null
  previewError.value = ''
  previewFor = null
  result.value = null
  current.value = 0
}
</script>

<template>
  <Modal :title="t('import.dialog_title')" wide @close="emit('close')">
    <form class="modal-form" @submit.prevent>
      <div class="modal-body space-y-5">
        <!-- Stepper -->
        <nav class="flex items-center gap-2 overflow-x-auto pb-1">
          <template v-for="(s, i) in steps" :key="s.id">
            <button
              type="button"
              data-no-loading
              @click="goTo(i)"
              :disabled="i > maxStep"
              class="flex items-center gap-1.5 text-[13px] font-semibold whitespace-nowrap transition disabled:cursor-not-allowed"
              :class="i < current ? 'text-emerald-600 dark:text-emerald-400'
                : i === current ? 'text-brand-700 dark:text-brand-300'
                : 'text-faint'"
            >
              <span
                class="w-4 h-4 rounded-full inline-flex items-center justify-center flex-shrink-0"
                :class="i < current ? 'bg-emerald-500 text-white'
                  : i === current ? 'bg-brand text-white'
                  : 'border-[1.5px] border-line'"
              >
                <svg v-if="i <= current" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              </span>
              {{ t(s.label) }}
            </button>
            <span v-if="i < steps.length - 1" class="flex-1 min-w-[16px] h-px bg-line"></span>
          </template>
        </nav>

        <!-- ── Step 1 · File ─────────────────────────────────────────── -->
        <template v-if="current === 0">
          <div
            class="border-[1.5px] border-dashed rounded-xl bg-surface-2 p-6 text-center transition-colors"
            :class="dragging ? 'border-brand' : 'border-line hover:border-brand/50'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
          >
            <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv,.txt" class="hidden" @change="pick" />
            <p class="text-xs text-faint italic mb-3">{{ t('import.drop_hint') }}<br />{{ t('import.file_hint') }}</p>
            <button type="button" @click="fileInput?.click()" class="btn-primary btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
              {{ file ? t('import.choose_different_file') : t('import.choose_file') }}
            </button>
          </div>

          <div v-if="file" class="form-group">
            <label class="label">{{ t('import.selected_file') }}</label>
            <div class="relative">
              <div class="input pr-10 truncate flex items-center">{{ file.name }}</div>
              <button
                type="button"
                @click="reset"
                :title="t('import.clear')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-faint hover:text-red-500 transition"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <p class="text-xs text-faint mt-1.5">{{ t('import.file_size_kb', { size: (file.size / 1024).toFixed(0) }) }}</p>
          </div>

          <label class="flex flex-wrap items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="generateQr" class="w-4 h-4 rounded border-line text-brand focus:ring-brand" />
            <span class="text-[13px] font-semibold text-brand-700 dark:text-brand-300">{{ t('import.generate_qr') }}</span>
            <span class="text-xs text-faint">{{ t('import.generate_qr_hint') }}</span>
          </label>
        </template>

        <!-- ── Step 2 · Photos ───────────────────────────────────────── -->
        <template v-if="current === 1">
          <!-- One photo per asset group from the chosen file (category + asset
               name), laid out like the photo list below. -->
          <div v-if="previewing || groups.length" class="form-group">
            <label class="label">{{ t('import.groups_title') }}</label>
            <span class="label-sub">{{ t('import.groups_hint') }}</span>
            <p v-if="previewing" class="text-xs text-faint">{{ t('import.counting') }}</p>
            <div v-else class="space-y-2">
              <div v-for="g in groups" :key="g.key" class="image-preview items-center">
                <div class="image-preview-thumb" style="aspect-ratio: 4 / 3">
                  <img v-if="groupPhotos[g.key]" :src="groupPhotos[g.key].url" alt="" />
                  <div v-else class="w-full h-full flex items-center justify-center text-faint">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><polyline points="21 15 16 10 5 21" /></svg>
                  </div>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-[13px] font-semibold text-fg truncate">{{ g.category }} — {{ g.name }}</p>
                  <p class="text-xs text-muted mt-0.5">
                    {{ t('import.group_count', { count: g.count }) }}
                    <!-- Once a photo is chosen: its name and size, as in the photo list below. -->
                    <span v-if="groupPhotos[g.key]" class="text-faint">· {{ groupPhotos[g.key].file.name }} · {{ sizeLabel(groupPhotos[g.key].file) }}</span>
                  </p>
                  <div class="flex flex-wrap gap-2 mt-2.5">
                    <template v-if="groupPhotos[g.key]">
                      <button type="button" class="btn-ghost btn-sm" @click="recropGroup(g.key)">{{ t('image.recrop') }}</button>
                      <button type="button" class="btn-ghost btn-sm" @click="chooseGroupImage(g.key)">{{ t('image.change') }}</button>
                      <button type="button" class="btn-ghost btn-sm" @click="removeGroupPhoto(g.key)">{{ t('image.remove') }}</button>
                    </template>
                    <button v-else type="button" class="btn-ghost btn-sm" @click="chooseGroupImage(g.key)">{{ t('import.choose_image') }}</button>
                  </div>
                </div>
              </div>
            </div>
            <input ref="groupInput" type="file" accept="image/jpeg,image/png" class="hidden" @change="onGroupPicked" />
          </div>

          <p class="text-sm text-muted">{{ t('import.images_hint') }}</p>

          <div
            class="border-[1.5px] border-dashed rounded-xl bg-surface-2 p-6 text-center transition-colors"
            :class="imagesDragging ? 'border-brand' : 'border-line hover:border-brand/50'"
            @dragover.prevent="imagesDragging = true"
            @dragleave.prevent="imagesDragging = false"
            @drop.prevent="onDropImages"
          >
            <input ref="imagesInput" type="file" accept="image/jpeg,image/png" multiple class="hidden" @change="pickImages" />
            <input ref="changeInput" type="file" accept="image/jpeg,image/png" class="hidden" @change="onChangePicked" />
            <p class="text-xs text-faint italic mb-3">{{ t('import.images_drop_hint') }}<br />{{ t('import.images_file_hint') }}</p>
            <button type="button" @click="imagesInput?.click()" class="btn-primary btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 4.5h18M3.75 4.5v15A2.25 2.25 0 006 21.75h12a2.25 2.25 0 002.25-2.25v-15" /></svg>
              {{ t('import.choose_images') }}
            </button>
          </div>

          <div v-if="images.length" class="form-group">
            <label class="label">{{ t('import.images_selected', { count: images.length }) }}</label>
            <!-- Each photo as in the photo box: the picture, its name and size,
                 then Crop again / Change / Remove. -->
            <div class="space-y-2">
              <div v-for="img in images" :key="img.id" class="image-preview">
                <div class="image-preview-thumb" style="aspect-ratio: 4 / 3">
                  <img :src="img.url" alt="" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-[13px] font-semibold text-fg truncate">{{ img.file.name }}</p>
                  <p class="text-xs text-muted mt-0.5">{{ sizeLabel(img.file) }}</p>
                  <div class="flex flex-wrap gap-2 mt-2.5">
                    <button type="button" class="btn-ghost btn-sm" @click="recropImage(img)">{{ t('image.recrop') }}</button>
                    <button type="button" class="btn-ghost btn-sm" @click="changeImage(img)">{{ t('image.change') }}</button>
                    <button type="button" class="btn-ghost btn-sm" @click="removeImage(img.id)">{{ t('image.remove') }}</button>
                  </div>
                </div>
              </div>
            </div>
            <button type="button" @click="clearImages" class="btn-ghost btn-sm mt-2">{{ t('import.clear_images') }}</button>
          </div>
        </template>

        <!-- ── Step 3 · before the import: what is about to be imported ── -->
        <template v-if="current === 2 && !result">
          <p class="text-sm text-muted">{{ t('import.ready_hint') }}</p>

          <div class="rounded-xl border border-line divide-y divide-line">
            <div class="flex items-center justify-between gap-4 px-4 py-3">
              <span class="text-[13px] text-muted flex-shrink-0">{{ t('import.selected_file') }}</span>
              <span class="text-[13px] font-semibold text-fg truncate">
                {{ file?.name }}
                <span class="font-normal text-faint">· {{ t('import.file_size_kb', { size: ((file?.size || 0) / 1024).toFixed(0) }) }}</span>
              </span>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3">
              <span class="text-[13px] text-muted flex-shrink-0">{{ t('import.total_assets') }}</span>
              <span class="text-[13px] font-semibold text-fg">
                <template v-if="previewing">{{ t('import.counting') }}</template>
                <template v-else-if="preview">
                  {{ preview.assets }}
                  <span v-if="preview.assets !== preview.rows" class="font-normal text-faint">· {{ t('import.from_rows', { count: preview.rows }) }}</span>
                </template>
                <template v-else>—</template>
              </span>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3">
              <span class="text-[13px] text-muted flex-shrink-0">{{ t('import.step_photos') }}</span>
              <span class="text-[13px] font-semibold text-fg">
                {{ photoCount ? t('import.images_selected', { count: photoCount }) : t('import.no_photos') }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3">
              <span class="text-[13px] text-muted flex-shrink-0">{{ t('import.qr_codes') }}</span>
              <span class="text-[13px] font-semibold text-fg">{{ generateQr ? t('import.qr_yes') : t('import.qr_no') }}</span>
            </div>
          </div>

          <p v-if="previewError" class="text-xs text-red-600 dark:text-red-400">{{ previewError }}</p>
          <p v-if="importing && generateQr" class="text-xs text-faint">{{ t('import.qr_wait_hint') }}</p>
        </template>

        <!-- ── Step 3 · Complete ─────────────────────────────────────── -->
        <template v-if="current === 2 && result">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-3 text-center">
              <p class="font-display text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ result.created }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.added') }}</p>
            </div>
            <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 p-3 text-center">
              <p class="font-display text-2xl font-bold text-blue-700 dark:text-blue-300">{{ result.updated }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.updated') }}</p>
            </div>
            <div class="rounded-xl bg-surface-2 p-3 text-center">
              <p class="font-display text-2xl font-bold text-fg">{{ result.skipped }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.skipped') }}</p>
            </div>
            <div class="rounded-xl p-3 text-center" :class="result.errors.length ? 'bg-red-50 dark:bg-red-500/10' : 'bg-surface-2'">
              <p class="font-display text-2xl font-bold" :class="result.errors.length ? 'text-red-700 dark:text-red-300' : 'text-fg'">{{ result.errors.length }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.errors') }}</p>
            </div>
          </div>

          <p v-if="result.images_attached" class="text-sm text-muted">
            {{ t('import.images_attached_message', { count: result.images_attached }) }}
          </p>

          <!-- What landed in the register, laid out like the Add Asset table:
               the code chip, then the name with category and description. -->
          <div v-if="result.assets?.length" class="form-group">
            <label class="label">{{ t('import.imported_assets', { count: result.assets.length }) }}</label>
            <div class="max-h-72 overflow-auto rounded-xl border border-line">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>{{ t('assets.id_col') }}</th>
                    <th>{{ t('assets.description_col') }}</th>
                    <th>{{ t('assets.location_col') }}</th>
                    <th class="text-center">{{ t('common.status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in result.assets" :key="a.id">
                    <td class="whitespace-nowrap"><span class="id-chip">{{ a.asset_code }}</span></td>
                    <td>
                      <div class="max-w-[12rem]">
                        <p class="font-semibold text-fg truncate">{{ a.name }}</p>
                        <p v-if="a.category || a.description" class="text-xs text-faint truncate mt-0.5">
                          {{ [a.category, a.description].filter(Boolean).join(' · ') }}
                        </p>
                      </div>
                    </td>
                    <td class="font-medium text-fg">{{ a.location || '—' }}</td>
                    <td class="text-center">
                      <span class="badge" :class="a.result === 'added' ? 'badge-success' : 'badge-info'">
                        {{ a.result === 'added' ? t('import.added') : t('import.updated') }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="result.images_unmatched?.length" class="form-group">
            <label class="label">{{ t('import.images_unmatched') }}</label>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-line divide-y divide-line">
              <p v-for="(name, i) in result.images_unmatched" :key="i" class="px-3 py-2 text-xs text-muted truncate">{{ name }}</p>
            </div>
          </div>

          <div v-if="result.errors.length" class="form-group">
            <label class="label">{{ t('import.failed_rows') }}</label>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-line divide-y divide-line">
              <p v-for="(err, i) in result.errors" :key="i" class="px-3 py-2 text-xs text-muted">{{ err }}</p>
            </div>
          </div>
        </template>
      </div>

      <!-- Footer like every form dialog: actions right, primary last; the
           template download sits on the left. -->
      <div class="modal-footer">
        <button v-if="current !== 2" type="button" @click="downloadTemplate" class="btn-subtle btn-sm mr-auto">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
          {{ t('import.download_template') }}
        </button>

        <template v-if="current === 0">
          <button type="button" class="btn-ghost" @click="emit('close')">{{ t('common.cancel') }}</button>
          <button type="button" @click="goTo(1)" :disabled="!file" class="btn-primary">{{ t('import.next') }}</button>
        </template>

        <template v-if="current === 1">
          <button type="button" @click="goTo(0)" class="btn-ghost">{{ t('common.back') }}</button>
          <button type="button" @click="goTo(2)" :disabled="!file" class="btn-primary">{{ t('import.next') }}</button>
        </template>

        <template v-if="current === 2 && !result">
          <button type="button" @click="goTo(1)" :disabled="importing" class="btn-ghost">{{ t('common.back') }}</button>
          <button type="button" @click="submit" :disabled="!file || importing" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            {{ t('import.import_assets') }}
          </button>
        </template>

        <template v-if="current === 2 && result">
          <button type="button" @click="reset" class="btn-ghost">{{ t('import.import_another') }}</button>
          <button type="button" @click="emit('close')" class="btn-primary">{{ t('import.view_register') }}</button>
        </template>
      </div>
    </form>

    <ImageCropper
      v-if="cropping"
      :key="cropping.id"
      :file="cropping.file"
      @apply="onPhotoCropped"
      @cancel="skipPhoto"
    />
  </Modal>
</template>
