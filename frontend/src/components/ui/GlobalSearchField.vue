<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()
const q = ref('')

function submit() {
  if (!q.value.trim()) return
  router.push({ name: 'search', query: { q: q.value.trim() } })
}
</script>

<template>
  <v-text-field
    v-model="q"
    :placeholder="t('header.search_placeholder')"
    prepend-inner-icon="mdi-magnify"
    density="compact"
    variant="solo-filled"
    flat
    hide-details
    rounded="lg"
    class="pepy-global-search"
    style="width: 100%"
    @keydown.enter="submit"
  />
</template>

<style scoped>
.pepy-global-search :deep(.v-field) {
  background: rgb(var(--v-theme-background));
}
</style>
