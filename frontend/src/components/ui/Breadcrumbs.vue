<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  section: { type: String, default: null },
  page: { type: String, default: null },
})

const items = computed(() => {
  const list = [{ title: t('nav.dashboard'), to: '/' }]
  if (props.section) list.push({ title: props.section, disabled: true })
  if (props.page) list.push({ title: props.page, disabled: true })
  return list
})
</script>

<template>
  <v-breadcrumbs v-if="page" :items="items" density="compact" class="pa-0" />
</template>
