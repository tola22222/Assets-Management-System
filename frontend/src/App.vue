<script setup>
import ToastHost from './components/ui/ToastHost.vue'
import LoadingOverlay from './components/common/LoadingOverlay.vue'
import NotificationSnackbar from './components/common/NotificationSnackbar.vue'
import ConfirmDialog from './components/common/ConfirmDialog.vue'
</script>

<template>
  <v-app>
    <RouterView />
    <ToastHost />
    <LoadingOverlay />
    <NotificationSnackbar />
    <ConfirmDialog />
  </v-app>
</template>
