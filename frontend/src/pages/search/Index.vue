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
      <div>
        <h1 class="text-xl font-bold text-ink tracking-tight">Search</h1>
        <p class="text-gray-500 text-sm mt-0.5">Search across assets, staff, locations and more</p>
      </div>

      <form @submit.prevent="search" class="flex gap-3">
        <input v-model="q" placeholder="Search…" class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:border-brand" />
        <button type="submit" :disabled="loading" class="bg-brand hover:bg-brand-dark disabled:opacity-60 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition">Search</button>
      </form>

      <div v-if="results" class="space-y-4">
        <div v-for="(items, type) in results" :key="type">
          <template v-if="items.length">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">{{ labels[type] || type }}</p>
            <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
              <div v-for="item in items" :key="item.id" class="p-4 text-sm text-ink">{{ displayName(type, item) }}</div>
            </div>
          </template>
        </div>
        <p v-if="Object.values(results).every((v) => !v.length)" class="text-gray-400 text-sm text-center py-8">No results found.</p>
      </div>
    </div>
  </AppLayout>
</template>
