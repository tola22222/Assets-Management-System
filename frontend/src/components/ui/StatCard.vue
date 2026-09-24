<script setup>
defineProps({
  value: { type: [String, Number], required: true },
  label: { type: String, required: true },
  badge: { type: String, default: null },
})
</script>

<template>
  <!-- KPI tile, per the stat-tile contract: sentence-case label on top, the
       value below in the app's own sans at semibold with proportional figures
       (no tabular-nums on a standalone number). The value row is pushed to the
       bottom (flex column + mt-auto) so values line up across a row even when
       one label wraps. Colour stays in text tokens. -->
  <div class="card p-5 flex flex-col">
    <p class="text-sm font-medium text-muted leading-snug">{{ label }}</p>

    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 mt-auto pt-4">
      <span class="font-display text-[32px] font-semibold text-fg tracking-tight leading-none">{{ value }}</span>
      <span v-if="badge" class="text-[11px] font-semibold bg-accent-100 text-accent-dark dark:bg-accent/15 dark:text-accent-light px-2 py-1 rounded-full whitespace-nowrap">
        {{ badge }}
      </span>
    </div>
  </div>
</template>
