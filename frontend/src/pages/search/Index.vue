<script setup>
import { ref } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const q = ref('')
const results = ref(null)
const loading = ref(false)

const labels = {
  assets: 'Assets', staff: 'Staff', categories: 'Categories',
  suppliers: 'Suppliers', locations: 'Locations', programs: 'Programs', users: 'Users',
}

function displayName(type, item) {
  if (type === 'staff') return item.full_name
  return item.name || item.asset_code
}

async function search() {
  if (q.value.length < 2) {
    toast.error('Please enter at least 2 characters.')
    return
  }
  loading.value = true
  try {
    const { data } = await http.get('/search', { params: { q: q.value } })
    results.value = data
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-3xl mx-auto space-y-6">
      <form @submit.prevent="search" class="flex gap-3">
        <input v-model="q" placeholder="Search…" class="flex-1 border border-line rounded-xl px-3.5 py-2.5 text-sm bg-surface focus:outline-none focus:border-brand" />
        <button type="submit" :disabled="loading" class="btn-primary">Search</button>
      </form>

      <div v-if="results" class="space-y-4">
        <div v-for="(items, type) in results" :key="type">
          <template v-if="items.length">
            <p class="text-xs font-semibold text-faint uppercase tracking-wide mb-2">{{ labels[type] || type }}</p>
            <div class="bg-surface rounded-2xl border border-line divide-y divide-line">
              <div v-for="item in items" :key="item.id" class="p-4 text-sm text-fg">{{ displayName(type, item) }}</div>
            </div>
          </template>
        </div>
        <p v-if="Object.values(results).every((v) => !v.length)" class="text-faint text-sm text-center py-8">No results found.</p>
      </div>
    </div>
  </AppLayout>
</template>
