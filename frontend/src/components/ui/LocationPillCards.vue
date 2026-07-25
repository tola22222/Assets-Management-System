<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  locations: { type: Array, required: true }, // [{ location, total }]
})
</script>

<template>
  <v-row v-if="locations.length" dense>
    <v-col v-for="loc in locations" :key="loc.location" cols="6" sm="3" lg="2" class="d-flex">
      <v-sheet rounded="lg" border class="px-3 py-3 flex-grow-1">
        <p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase text-truncate" :title="loc.location">{{ loc.location }}</p>
        <p class="text-h5 font-weight-bold mt-1">{{ loc.total }}</p>
      </v-sheet>
    </v-col>
  </v-row>
  <div v-else class="text-body-2 text-medium-emphasis">{{ t('dashboard.no_stock') }}</div>
</template>
