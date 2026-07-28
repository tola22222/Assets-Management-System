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

const palette = ['#3f7a5f', '#c9a24b', '#5b9bd5', '#e07a5f', '#8a7bb8', '#4bbf9a']

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
    <div class="relative w-32 h-32 flex-shrink-0">
      <Doughnut :data="chartData" :options="chartOptions" />
      <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
        <span class="font-display text-xl font-bold text-fg leading-none">{{ total }}</span>
        <span class="text-[11px] text-faint mt-1">{{ totalLabel }}</span>
      </div>
    </div>
    <div class="flex-1 space-y-3 min-w-0">
      <div v-for="(segment, i) in segments" :key="segment.category" class="flex items-center justify-between gap-3 text-sm">
        <div class="flex items-center gap-2 min-w-0">
          <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: palette[i % palette.length] }"></span>
          <span class="font-semibold text-fg truncate">{{ segment.category }}</span>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
          <span class="font-semibold text-fg">{{ segment.count }}</span>
          <span class="w-9 text-right text-faint">{{ segment.percentage }}%</span>
        </div>
      </div>
    </div>
  </div>
</template>
