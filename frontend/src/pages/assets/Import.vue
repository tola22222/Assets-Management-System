<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const toast = useToastStore()

const file = ref(null)
const generateQr = ref(true)
const importing = ref(false)
const result = ref(null)

watch(file, () => { result.value = null })

async function submit() {
  if (!file.value) return
  importing.value = true
  result.value = null
  try {
    const fd = new FormData()
    fd.append('file', file.value)
    fd.append('generate_qr', generateQr.value ? '1' : '0')
    const { data } = await http.post('/assets/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    result.value = data
    toast.success(t('import.success_message', { created: data.created, updated: data.updated }))
  } catch (e) {
    toast.error(e.response?.data?.message || t('import.failed'))
  } finally {
    importing.value = false
  }
}

async function downloadTemplate() {
  const { data } = await http.get('/assets/import/template', { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = 'asset_import_template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function reset() {
  file.value = null
  result.value = null
}
</script>

<template>
  <v-container fluid class="pa-0 d-flex flex-column ga-6" style="max-width: 720px">
      <AppPageHeader :title="t('import.title')" :subtitle="t('import.subtitle')" />

      <v-btn to="/assets" variant="text" prepend-icon="mdi-arrow-left" class="align-self-start">{{ t('import.back') }}</v-btn>

      <!-- How it works -->
      <v-card rounded="lg" variant="flat" border>
        <v-card-text class="d-flex flex-column ga-3">
          <h3 class="text-subtitle-1 font-weight-bold">{{ t('import.how_it_works') }}</h3>
          <ul class="text-body-2 text-medium-emphasis d-flex flex-column ga-1 pl-0" style="list-style: none">
            <li class="d-flex ga-2"><span class="text-primary">•</span> {{ t('import.step1') }}</li>
            <li class="d-flex ga-2"><span class="text-primary">•</span> {{ t('import.step2') }}</li>
            <li class="d-flex ga-2"><span class="text-primary">•</span> {{ t('import.step_location') }}</li>
            <li class="d-flex ga-2"><span class="text-primary">•</span> {{ t('import.step3') }}</li>
            <li class="d-flex ga-2"><span class="text-primary">•</span> {{ t('import.step4') }}</li>
          </ul>
          <v-btn variant="text" size="small" class="align-self-start" @click="downloadTemplate">{{ t('import.download_template') }}</v-btn>
        </v-card-text>
      </v-card>

      <!-- Uploader -->
      <v-card rounded="lg" variant="flat" border>
        <v-card-text class="d-flex flex-column ga-4">
          <v-file-input
            v-model="file"
            :label="t('import.choose_file')"
            :hint="t('import.file_hint')"
            persistent-hint
            accept=".xlsx,.xls,.csv,.txt"
            variant="outlined"
            prepend-icon=""
            prepend-inner-icon="mdi-tray-arrow-up"
            show-size
          />

          <v-checkbox v-model="generateQr" :label="t('import.generate_qr')" :hint="t('import.generate_qr_hint')" persistent-hint hide-details="auto" />

          <div class="d-flex align-center ga-3">
            <v-btn color="primary" variant="flat" :loading="importing" :disabled="!file" @click="submit">{{ t('import.import_assets') }}</v-btn>
            <v-btn v-if="file && !importing" variant="text" @click="reset">{{ t('import.clear') }}</v-btn>
          </div>
          <p v-if="importing && generateQr" class="text-caption text-medium-emphasis">{{ t('import.qr_wait_hint') }}</p>
        </v-card-text>
      </v-card>

      <!-- Result -->
      <v-card v-if="result" rounded="lg" variant="flat" border>
        <v-card-text class="d-flex flex-column ga-4">
          <h3 class="text-subtitle-1 font-weight-bold">{{ t('import.complete') }}</h3>
          <v-row dense>
            <v-col cols="6" sm="3">
              <v-sheet rounded="lg" color="success" variant="tonal" class="pa-3 text-center">
                <p class="text-h5 font-weight-bold">{{ result.created }}</p>
                <p class="text-caption mt-1">{{ t('import.added') }}</p>
              </v-sheet>
            </v-col>
            <v-col cols="6" sm="3">
              <v-sheet rounded="lg" color="info" variant="tonal" class="pa-3 text-center">
                <p class="text-h5 font-weight-bold">{{ result.updated }}</p>
                <p class="text-caption mt-1">{{ t('import.updated') }}</p>
              </v-sheet>
            </v-col>
            <v-col cols="6" sm="3">
              <v-sheet rounded="lg" color="surface-variant" class="pa-3 text-center">
                <p class="text-h5 font-weight-bold">{{ result.skipped }}</p>
                <p class="text-caption mt-1">{{ t('import.skipped') }}</p>
              </v-sheet>
            </v-col>
            <v-col cols="6" sm="3">
              <v-sheet rounded="lg" :color="result.errors.length ? 'error' : 'surface-variant'" :variant="result.errors.length ? 'tonal' : 'flat'" class="pa-3 text-center">
                <p class="text-h5 font-weight-bold">{{ result.errors.length }}</p>
                <p class="text-caption mt-1">{{ t('import.errors') }}</p>
              </v-sheet>
            </v-col>
          </v-row>

          <div v-if="result.errors.length" class="d-flex flex-column ga-2">
            <p class="text-body-2 font-weight-semibold">{{ t('import.failed_rows') }}</p>
            <v-sheet rounded="lg" border class="d-flex flex-column" style="max-height: 192px; overflow-y: auto">
              <p v-for="(err, i) in result.errors" :key="i" class="px-3 py-2 text-caption text-medium-emphasis mb-0 border-b">{{ err }}</p>
            </v-sheet>
          </div>

          <div class="d-flex ga-3">
            <v-btn to="/assets" color="primary" variant="flat" size="small">{{ t('import.view_register') }}</v-btn>
            <v-btn variant="text" size="small" @click="reset">{{ t('import.import_another') }}</v-btn>
          </div>
        </v-card-text>
      </v-card>
  </v-container>
</template>
