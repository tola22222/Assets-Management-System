<template>
  <div class="min-h-screen flex items-center justify-center bg-cream px-4">
    <div class="w-full max-w-sm">
      <div class="flex items-center gap-3 mb-8 justify-center">
        <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center font-display font-bold text-brand-dark text-lg">P</div>
        <div>
          <p class="font-display font-bold text-brand text-lg leading-tight">PEPY Assets</p>
          <p class="text-gray-400 text-xs">Empowering Youth</p>
        </div>
      </div>
      <slot />
    </div>
  </div>
</template>
