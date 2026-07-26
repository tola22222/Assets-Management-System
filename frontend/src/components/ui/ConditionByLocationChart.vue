<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const props = defineProps({
  rows: { type: Array, required: true }, // [{ location, good, fair, broken, lost }]
})

const COLORS = { good: '#1F4B43', fair: '#C79A46', broken: '#B5573D', lost: '#616A62' }
const AXIS_INK = '#8a8a78'
const GRID_INK = 'rgba(138, 138, 120, 0.15)'

const chartData = computed(() => ({
  labels: props.rows.map((r) => r.location),
  datasets: ['good', 'fair', 'broken', 'lost'].map((key) => ({
    label: key.charAt(0).toUpperCase() + key.slice(1),
    data: props.rows.map((r) => r[key]),
    backgroundColor: COLORS[key],
    borderRadius: 2,
  })),
}))

const chartOptions = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 }, color: AXIS_INK } },
    tooltip: { mode: 'index', intersect: false },
  },
  scales: {
    x: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: AXIS_INK, font: { size: 11 } }, grid: { color: GRID_INK } },
    y: { stacked: true, ticks: { color: AXIS_INK, font: { size: 11 } }, grid: { display: false } },
  },
}
</script>

<template>
  <div :style="{ height: Math.max(160, rows.length * 36) + 'px' }">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
