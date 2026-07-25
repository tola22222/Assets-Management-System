<script setup>
import { useTheme } from '../../composables/useTheme'

const { isDark, toggle } = useTheme()
</script>

<template>
  <v-btn
    :icon="isDark ? 'mdi-white-balance-sunny' : 'mdi-weather-night'"
    variant="text"
    :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    @click="toggle"
  />
</template>
