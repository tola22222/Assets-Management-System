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
  <button @click="router.push('/notifications')" class="relative w-9 h-9 rounded-xl flex items-center justify-center hover:bg-gray-100 transition">
    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    <span v-if="count > 0" class="absolute top-1 right-1 bg-red-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
      {{ count > 9 ? '9+' : count }}
    </span>
  </button>
</template>
