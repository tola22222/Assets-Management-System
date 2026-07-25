<script setup>
import { useLoading } from '../../composables/useLoading'

const { active, message } = useLoading()
</script>

<template>
  <v-overlay
    :model-value="active"
    persistent
    scrim="#000"
    class="align-center justify-center"
    style="z-index: 9999"
  >
    <div class="d-flex flex-column align-center ga-3">
      <v-progress-circular indeterminate size="56" width="5" color="white" />
      <span v-if="message" class="text-white text-body-2">{{ message }}</span>
    </div>
  </v-overlay>
</template>
