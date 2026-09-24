<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip)

const props = defineProps({
  data: { type: Array, required: true }, // [{ label, count }], oldest first; the last bucket is the current period
  period: { type: String, default: 'month' },
})

const { t } = useI18n()

// The chart's own colours, unchanged: one fixed fill for every bar and a
// recessive mid-tone for axis text and gridlines that reads on both the light
// and dark surfaces (the same fixed-hex approach DonutChart uses).
const AXIS_INK = '#8a8a78'
const GRID_INK = 'rgba(138, 138, 120, 0.15)'
const BAR_FILL = '#356458'

const lastIndex = computed(() => props.data.length - 1)

function axisLabel(label) {
  if (props.period === 'day') {
    return new Date(`${label}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  }
  if (props.period === 'month') {
    const [y, m] = label.split('-')
    return new Date(y, m - 1, 1).toLocaleDateString(undefined, { month: 'short', year: '2-digit' })
  }
  return label
}

// The tooltip names the period in full ("September 2026", "Wed, Sep 24, 2026").
function fullLabel(label) {
  if (props.period === 'day') {
    return new Date(`${label}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
  }
  if (props.period === 'month') {
    const [y, m] = label.split('-')
    return new Date(y, m - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
  }
  return label
}

// Selective direct label: only the peak gets its value on the cap; the axis
// and the tooltip carry the rest (never a number on every bar).
const peakIndex = computed(() => {
  let best = -1
  props.data.forEach((d, i) => { if (d.count > 0 && (best < 0 || d.count > props.data[best].count)) best = i })
  return best
})
const peakLabel = {
  id: 'peakLabel',
  afterDatasetsDraw(chart, _args, opts) {
    const bar = chart.getDatasetMeta(0).data[opts.index]
    if (!bar || opts.index < 0) return
    const { ctx } = chart
    ctx.save()
    ctx.fillStyle = opts.color
    ctx.font = `600 11px ${getComputedStyle(chart.canvas).fontFamily}`
    ctx.textAlign = 'center'
    ctx.textBaseline = 'bottom'
    ctx.fillText(String(opts.value), bar.x, bar.y - 6)
    ctx.restore()
  },
}

const chartData = computed(() => {
  return {
    labels: props.data.map((d) => axisLabel(d.label)),
    datasets: [
      {
        data: props.data.map((d) => d.count),
        backgroundColor: BAR_FILL,
        // 4px rounded data-end, square at the baseline; thin marks (<= 24px)
        // that never fill the slot, so the leftover band is air.
        borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 },
        borderSkipped: 'start',
        maxBarThickness: 22,
        categoryPercentage: 0.7,
        barPercentage: 0.9,
      },
    ],
  }
})

const chartOptions = computed(() => {
  const font = { size: 11 }
  return {
    responsive: true,
    maintainAspectRatio: false,
    // Headroom so the peak's value label never clips at the top edge.
    layout: { padding: { top: 20 } },
    // The whole column band is the hover target, not just the bar itself —
    // a thin or zero-height bar is still easy to hit.
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false }, // one series: the card title names it
      peakLabel: { index: peakIndex.value, value: props.data[peakIndex.value]?.count, color: AXIS_INK },
      tooltip: {
        displayColors: false,
        cornerRadius: 8,
        padding: { x: 12, y: 10 },
        titleFont: { size: 11, weight: '600' },
        bodyFont: { size: 13, weight: '600' },
        callbacks: {
          title: (items) => {
            const i = items[0].dataIndex
            const name = fullLabel(props.data[i]?.label ?? '')
            return i === lastIndex.value ? `${name} · ${t('dashboard.trend_current')}` : name
          },
          label: (item) => t('dashboard.trend_tooltip', { n: item.raw }, item.raw),
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        border: { display: false },
        ticks: {
          autoSkip: true,
          maxRotation: 0,
          font: (ctx) => ({ ...font, weight: ctx.index === lastIndex.value ? '600' : '400' }),
          color: AXIS_INK,
        },
      },
      y: {
        beginAtZero: true,
        border: { display: false },
        // Hairline, solid, one step off the surface — recessive.
        grid: { color: GRID_INK, lineWidth: 1, drawTicks: false },
        ticks: { precision: 0, color: AXIS_INK, font, padding: 8, maxTicksLimit: 5 },
      },
    },
  }
})
</script>

<template>
  <div class="h-56">
    <Bar :data="chartData" :options="chartOptions" :plugins="[peakLabel]" />
  </div>
</template>
