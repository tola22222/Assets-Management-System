<script setup>
import { ref, onMounted, reactive } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const loading = ref(true)
const form = reactive({
  organization_name: '', system_name: '', theme_color: '#128a43', email: '', phone: '',
  address: '', qr_size: 300, locale: 'en', report_interval_months: 6,
})

async function loadSettings() {
  const { data } = await http.get('/settings')
  Object.keys(form).forEach((key) => {
    if (data[key] !== undefined) form[key] = data[key]
  })
  loading.value = false
}

async function handleSubmit() {
  try {
    await http.post('/settings', form)
    toast.success('Settings updated successfully.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not save settings.')
  }
}

onMounted(loadSettings)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-3xl mx-auto space-y-6">
      <div>
        <h1 class="text-xl font-bold text-ink tracking-tight">System Settings</h1>
        <p class="text-gray-500 text-sm mt-0.5">Organization information and preferences</p>
      </div>

      <form v-if="!loading" @submit.prevent="handleSubmit" class="bg-white rounded-2xl border border-gray-200 p-8 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Organization Name</label>
          <input v-model="form.organization_name" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">System Name</label>
          <input v-model="form.system_name" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Email</label>
            <input v-model="form.email" type="email" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Phone</label>
            <input v-model="form.phone" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Address</label>
          <textarea v-model="form.address" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Language</label>
            <select v-model="form.locale" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="en">English</option>
              <option value="km">ខ្មែរ</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">QR Code Size</label>
            <input v-model.number="form.qr_size" type="number" min="100" max="1000" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Theme Color</label>
            <input v-model="form.theme_color" type="color" class="w-full h-11 border border-gray-200 rounded-xl cursor-pointer p-1" />
          </div>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Asset Count Report Interval (months)</label>
          <input v-model.number="form.report_interval_months" type="number" min="1" max="24" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="pt-2">
          <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition">Save Settings</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
