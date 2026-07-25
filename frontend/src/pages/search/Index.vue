<script setup>
import { ref } from 'vue'
import http from '../../api/http'
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
  <v-container fluid class="pa-0 d-flex flex-column ga-6" style="max-width: 640px">
      <v-form @submit.prevent="search" class="d-flex ga-3">
        <v-text-field v-model="q" placeholder="Search…" hide-details />
        <v-btn type="submit" color="primary" variant="flat" :loading="loading">Search</v-btn>
      </v-form>

      <div v-if="results" class="d-flex flex-column ga-4">
        <div v-for="(items, type) in results" :key="type">
          <template v-if="items.length">
            <p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase mb-2">{{ labels[type] || type }}</p>
            <v-card rounded="lg" variant="flat" border>
              <template v-for="(item, i) in items" :key="item.id">
                <v-divider v-if="i > 0" />
                <div class="pa-4 text-body-2">{{ displayName(type, item) }}</div>
              </template>
            </v-card>
          </template>
        </div>
        <p v-if="Object.values(results).every((v) => !v.length)" class="text-body-2 text-medium-emphasis text-center py-8">No results found.</p>
      </div>
  </v-container>
</template>
