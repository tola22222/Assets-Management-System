<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import http from '../../api/http'

const router = useRouter()
const count = ref(0)
let timer = null

async function loadCount() {
  try {
    const { data } = await http.get('/notifications/unread-count')
    count.value = data.count
  } catch (e) {
    // ignore transient failures
  }
}

onMounted(() => {
  loadCount()
  timer = setInterval(loadCount, 30000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <v-btn icon variant="text" @click="router.push('/notifications')">
    <v-badge :content="count > 9 ? '9+' : count" color="error" :model-value="count > 0" floating>
      <v-icon icon="mdi-bell-outline" />
    </v-badge>
  </v-btn>
</template>
