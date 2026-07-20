<script setup>
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import logoUrl from '../assets/logo/Official PEPY Logo_Green.png'
</script>

<template>
  <div class="min-h-screen flex bg-canvas text-fg">
    <!-- Brand panel (desktop) -->
    <div class="hidden lg:flex w-1/2 relative overflow-hidden bg-brand-800 text-white flex-col justify-between p-12">
      <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-brand-light/30 blur-3xl"></div>
      <div class="absolute -bottom-32 -left-16 w-96 h-96 rounded-full bg-accent/10 blur-3xl"></div>

      <div class="relative flex items-center gap-3">
        <div class="w-11 h-11 flex items-center justify-center flex-shrink-0">
          <img :src="logoUrl" alt="PEPY" class="w-full h-full object-contain" />
        </div>
        <div>
          <p class="font-display font-bold text-lg leading-tight">PEPY Assets</p>
          <p class="text-white/50 text-xs">Empowering Youth · Siem Reap</p>
        </div>
      </div>

      <div class="relative">
        <h2 class="font-display text-3xl font-bold leading-snug">Every asset, accounted for.</h2>
        <p class="text-white/60 mt-4 max-w-md">
          Track, assign, transfer and verify fixed assets across the office and every learning center — all in one place.
        </p>
      </div>

      <p class="relative text-white/40 text-xs">© {{ new Date().getFullYear() }} PEPY · Asset Management System</p>
    </div>

    <!-- Form panel -->
    <div class="flex-1 flex flex-col items-center justify-center px-4 py-10 relative">
      <div class="absolute top-4 right-4">
        <ThemeToggle />
      </div>

      <div class="w-full max-w-sm">
        <div class="flex items-center gap-3 mb-8 lg:hidden">
          <div class="w-10 h-10 flex items-center justify-center flex-shrink-0">
            <img :src="logoUrl" alt="PEPY" class="w-full h-full object-contain" />
          </div>
          <div>
            <p class="font-display font-bold text-brand-600 dark:text-brand-300 text-lg leading-tight">PEPY Assets</p>
            <p class="text-faint text-xs">Empowering Youth</p>
          </div>
        </div>
        <slot />
      </div>
    </div>
  </div>
</template>
