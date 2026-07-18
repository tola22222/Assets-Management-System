<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js'

ChartJS.register(ArcElement, Tooltip)

const props = defineProps({
  segments: { type: Array, required: true }, // [{ category, count, percentage }]
  total: { type: [String, Number], required: true },
  totalLabel: { type: String, default: 'total items' },
})

const palette = ['#1f3d2e', '#2c5342', '#c9a24b', '#a35b4a', '#7a8c99', '#4a6670']

const chartData = computed(() => ({
  labels: props.segments.map((s) => s.category),
  datasets: [
    {
      data: props.segments.map((s) => s.count),
      backgroundColor: props.segments.map((_, i) => palette[i % palette.length]),
      borderWidth: 0,
    },
  ],
}))

const chartOptions = {
  cutout: '70%',
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
  responsive: true,
  maintainAspectRatio: true,
}
</script>

<template>
  <div class="flex items-center gap-8">
    <div class="relative w-40 h-40 flex-shrink-0">
      <Doughnut :data="chartData" :options="chartOptions" />
      <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
        <span class="font-display text-2xl font-bold text-ink">{{ total }}</span>
        <span class="text-xs text-gray-400">{{ totalLabel }}</span>
      </div>
    </div>
    <div class="flex-1 space-y-2.5 min-w-0">
      <div v-for="(segment, i) in segments" :key="segment.category" class="flex items-center justify-between gap-3 text-sm">
        <div class="flex items-center gap-2 min-w-0">
          <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: palette[i % palette.length] }"></span>
          <span class="font-semibold text-ink truncate">{{ segment.category }}</span>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0 text-gray-500">
          <span class="font-semibold text-ink">{{ segment.count }}</span>
          <span class="w-10 text-right">{{ segment.percentage }}%</span>
        </div>
      </div>
    </div>
  </div>
</template>
