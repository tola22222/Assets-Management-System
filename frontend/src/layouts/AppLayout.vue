<script setup>
import { ref } from 'vue'
import { useDisplay } from 'vuetify'
import AppNavigationDrawer from './AppNavigationDrawer.vue'
import AppHeader from './AppHeader.vue'

const { mdAndUp } = useDisplay()

const mobileOpen = ref(false)
const collapsed = ref(localStorage.getItem('sidebarCollapsed') === '1')

function toggleSidebar() {
  if (mdAndUp.value) {
    collapsed.value = !collapsed.value
    localStorage.setItem('sidebarCollapsed', collapsed.value ? '1' : '0')
  } else {
    mobileOpen.value = !mobileOpen.value
  }
}
</script>

<template>
  <AppNavigationDrawer
    :model-value="mdAndUp ? !collapsed : mobileOpen"
    :permanent="mdAndUp"
    :temporary="!mdAndUp"
    @update:model-value="(v) => { if (!mdAndUp) mobileOpen = v }"
  />

  <AppHeader :collapsed="collapsed" @toggle-sidebar="toggleSidebar" />

  <v-main>
    <RouterView />
  </v-main>
</template>
