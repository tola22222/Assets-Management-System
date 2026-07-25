<script setup>
import { computed, watch } from 'vue'
import { useTheme as useVuetifyTheme } from 'vuetify'
import { darkTheme, NConfigProvider } from 'naive-ui'
import ToastHost from './components/ui/ToastHost.vue'
import LoadingOverlay from './components/common/LoadingOverlay.vue'
import NotificationSnackbar from './components/common/NotificationSnackbar.vue'
import ConfirmDialog from './components/common/ConfirmDialog.vue'
import { useTheme } from './composables/useTheme'
import { useNaiveTheme } from './composables/useNaiveTheme'

const { isDark } = useTheme()
const { themeOverrides } = useNaiveTheme()
const naiveTheme = computed(() => (isDark.value ? darkTheme : null))

// Keeps Vuetify's theme (used by the Vuetify-based common/ components) in sync
// with the app's existing dark-mode toggle, which flips the .dark class on <html>.
const vuetifyTheme = useVuetifyTheme()
watch(isDark, (dark) => (vuetifyTheme.global.name.value = dark ? 'pepyDark' : 'pepyLight'), {
  immediate: true,
})
</script>

<template>
  <n-config-provider :theme="naiveTheme" :theme-overrides="themeOverrides">
    <RouterView />
    <ToastHost />
  </n-config-provider>

  <!-- Vuetify overlay-based globals (dialog/snackbar/overlay teleport to <body>,
       so this wrapper doesn't affect the Naive UI layout above). Mounted once. -->
  <v-app>
    <LoadingOverlay />
    <NotificationSnackbar />
    <ConfirmDialog />
  </v-app>
</template>
