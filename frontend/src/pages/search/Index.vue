<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const toast = useToastStore()
const q = ref('')
const results = ref(null)
const loading = ref(false)

const labels = computed(() => ({
  assets: t('search.type_assets'), staff: t('search.type_staff'), categories: t('search.type_categories'),
  suppliers: t('search.type_suppliers'), locations: t('search.type_locations'),
  programs: t('search.type_programs'), users: t('search.type_users'),
}))

function displayName(type, item) {
  if (type === 'staff') return item.full_name
  return item.name || item.asset_code
}

async function search() {
  if (q.value.length < 2) {
    toast.error(t('search.min_length'))
    return
  }
  loading.value = true
  try {
    const { data } = await http.get('/search', { params: { q: q.value } })
    results.value = data
  } catch (e) {
    results.value = null
    toast.error(errorMessage(e, t('search.failed')))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-3xl mx-auto space-y-6">
      <div>
        <h1 class="font-display text-xl font-bold text-fg tracking-tight">{{ t('search.title') }}</h1>
        <p class="text-muted text-sm mt-0.5">{{ t('search.subtitle') }}</p>
      </div>

      <form @submit.prevent="search" class="flex gap-3">
        <div class="flex-1">
          <SearchInput v-model="q" :placeholder="t('common.search_placeholder')" />
        </div>
        <button type="submit" :disabled="loading" class="btn-primary">{{ t('search.submit') }}</button>
      </form>

      <div v-if="results" class="space-y-4">
        <div v-for="(items, type) in results" :key="type">
          <template v-if="items.length">
            <p class="text-xs font-semibold text-faint uppercase tracking-wide mb-2">{{ labels[type] || type }}</p>
            <div class="card divide-y divide-line">
              <div v-for="item in items" :key="item.id" class="p-4 text-sm text-fg">{{ displayName(type, item) }}</div>
            </div>
          </template>
        </div>
        <p v-if="Object.values(results).every((v) => !v.length)" class="text-faint text-sm text-center py-8">{{ t('common.no_results') }}</p>
      </div>
    </div>
  </AppLayout>
</template>
