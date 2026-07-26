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

const palette = ['#1F4B43', '#C79A46', '#2E6358', '#B5573D', '#9B8A62', '#616A62']

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
  <div class="d-flex align-center ga-8">
    <div class="position-relative flex-shrink-0" style="width: 128px; height: 128px">
      <Doughnut :data="chartData" :options="chartOptions" />
      <div class="position-absolute d-flex flex-column align-center justify-center" style="inset: 0; pointer-events: none">
        <span class="text-h6 font-weight-bold font-display" style="line-height: 1">{{ total }}</span>
        <span class="text-caption text-medium-emphasis mt-1">{{ totalLabel }}</span>
      </div>
    </div>
    <div class="flex-grow-1 d-flex flex-column ga-3" style="min-width: 0">
      <div v-for="(segment, i) in segments" :key="segment.category" class="d-flex align-center justify-space-between ga-3 text-body-2">
        <div class="d-flex align-center ga-2" style="min-width: 0">
          <span class="rounded-circle flex-shrink-0" :style="{ backgroundColor: palette[i % palette.length], width: '8px', height: '8px' }"></span>
          <span class="font-weight-semibold text-truncate">{{ segment.category }}</span>
        </div>
        <div class="d-flex align-center ga-3 flex-shrink-0">
          <span class="font-weight-semibold">{{ segment.count }}</span>
          <span class="text-right text-medium-emphasis" style="width: 36px">{{ segment.percentage }}%</span>
        </div>
      </div>
    </div>
  </div>
</template>
