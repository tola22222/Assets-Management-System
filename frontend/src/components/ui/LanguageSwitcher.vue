<script setup>
import { useI18n } from 'vue-i18n'
import { useLocale } from '../../composables/useLocale'

const { t } = useI18n()
const { locale, setLocale } = useLocale()

const options = [
  { value: 'en', label: 'English' },
  { value: 'km', label: 'ខ្មែរ' },
]
</script>

<template>
  <v-menu>
    <template #activator="{ props }">
      <v-btn v-bind="props" icon variant="text" :title="t('header.language')">
        <v-icon icon="mdi-translate" />
      </v-btn>
    </template>
    <v-list density="compact" min-width="140">
      <v-list-item
        v-for="opt in options"
        :key="opt.value"
        :title="opt.label"
        :active="locale === opt.value"
        @click="setLocale(opt.value)"
      />
    </v-list>
  </v-menu>
</template>
