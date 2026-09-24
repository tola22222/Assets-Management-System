<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'

// "All locations" drop-down for a list page's filter bar. v-model is a
// location id as a string ('' = every site), so matchers should compare with
// String(row.location_id) === value. It loads the site list itself, so a page
// only binds the value — and fetches fresh on mount, so a site added on the
// Locations page shows up without a reload.
const { t } = useI18n()
defineProps({ modelValue: { type: [String, Number], default: '' } })
defineEmits(['update:modelValue'])

const locations = ref([])
onMounted(async () => {
  try {
    const { data } = await http.get('/locations')
    locations.value = data
  } catch {
    // Keep just "All locations": the page still works, only unfiltered.
  }
})
</script>

<template>
  <select
    :value="modelValue"
    class="filter-select"
    :aria-label="t('common.all_locations')"
    @change="$emit('update:modelValue', $event.target.value)"
  >
    <option value="">{{ t('common.all_locations') }}</option>
    <option v-for="l in locations" :key="l.id" :value="String(l.id)">{{ l.name }}</option>
  </select>
</template>
