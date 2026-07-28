<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip)

const props = defineProps({
  data: { type: Array, required: true }, // [{ label, count }]
  period: { type: String, default: 'month' },
})

// Recessive axis ink — one fixed mid-tone that reads acceptably on both the
// light cream and dark green surfaces, matching how DonutChart also uses
// fixed hex colors rather than live-reading CSS custom properties.
const AXIS_INK = '#8a8a78'
const GRID_INK = 'rgba(138, 138, 120, 0.15)'
const BAR_FILL = '#35634c'

function formatLabel(label) {
  if (props.period === 'day') {
    return new Date(`${label}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  }
  if (props.period === 'month') {
    const [y, m] = label.split('-')
    return new Date(y, m - 1, 1).toLocaleDateString(undefined, { month: 'short', year: '2-digit' })
  }
  return label
}

const chartData = computed(() => ({
  labels: props.data.map((d) => formatLabel(d.label)),
  datasets: [
    {
      data: props.data.map((d) => d.count),
      backgroundColor: BAR_FILL,
      borderRadius: 4,
      maxBarThickness: 28,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      displayColors: false,
      callbacks: {
        title: (items) => props.data[items[0].dataIndex]?.label ?? '',
        label: (item) => `${item.formattedValue} asset${item.raw === 1 ? '' : 's'} registered`,
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { autoSkip: true, maxRotation: 0, color: AXIS_INK, font: { size: 11 } },
    },
    y: {
      beginAtZero: true,
      ticks: { precision: 0, color: AXIS_INK, font: { size: 11 } },
      grid: { color: GRID_INK },
    },
  },
}))
</script>

<template>
  <div class="h-56">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
