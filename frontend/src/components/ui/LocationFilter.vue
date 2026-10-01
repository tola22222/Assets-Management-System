<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import SearchSelect from './SearchSelect.vue'

// "All locations" drop-down for a list page's filter bar. v-model is a
// location id as a string ('' = every site), so matchers should compare with
// String(row.location_id) === value. It loads the site list itself, so a page
// only binds the value — and fetches fresh on mount, so a site added on the
// Locations page shows up without a reload. Typing searches the site names
// (contains-match, see SearchSelect).
const { t } = useI18n()
defineProps({ modelValue: { type: [String, Number], default: '' } })
const emit = defineEmits(['update:modelValue'])

const locations = ref([])
const options = computed(() => locations.value.map((l) => ({ value: String(l.id), label: l.name, sub: l.code || undefined })))
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
  <SearchSelect
    class="min-w-[13rem]"
    :model-value="modelValue"
    :options="options"
    :empty-label="t('common.all_locations')"
    input-class="filter-select"
    :aria-label="t('common.all_locations')"
    @update:model-value="emit('update:modelValue', String($event ?? ''))"
  />
</template>
