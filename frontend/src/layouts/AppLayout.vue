<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen flex bg-cream">
    <aside class="w-64 bg-brand text-white flex flex-col flex-shrink-0">
      <div class="flex items-center gap-3 px-5 py-6">
        <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center font-display font-bold text-brand-dark">P</div>
        <div class="min-w-0">
          <p class="font-display font-bold leading-tight truncate">PEPY Assets</p>
          <p class="text-white/50 text-xs truncate">Empowering Youth · Siem Reap</p>
        </div>
      </div>

      <nav class="flex-1 px-3 space-y-6 mt-2">
        <div>
          <p class="px-2 text-[11px] font-semibold text-white/40 uppercase tracking-wider mb-1.5">Overview</p>
          <RouterLink to="/" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-semibold bg-white/10 text-white">
            Dashboard
          </RouterLink>
        </div>

        <div>
          <p class="px-2 text-[11px] font-semibold text-white/40 uppercase tracking-wider mb-1.5">Manage</p>
          <div class="space-y-0.5">
            <RouterLink to="/assets" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Asset Register</RouterLink>
            <RouterLink to="/categories" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Categories</RouterLink>
            <RouterLink to="/locations" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Locations</RouterLink>
            <RouterLink to="/asset-stocks" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Stock Movements</RouterLink>
            <RouterLink to="/asset-assignments" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Assignments</RouterLink>
            <RouterLink to="/asset-transfers" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Transfers</RouterLink>
            <RouterLink to="/asset-returns" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Returns</RouterLink>
            <RouterLink to="/asset-verifications" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Verifications</RouterLink>
            <RouterLink to="/asset-disposals" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/70 hover:bg-white/10 hover:text-white transition" active-class="bg-white/10 text-white font-semibold">Disposals</RouterLink>
          </div>
        </div>

        <div>
          <p class="px-2 text-[11px] font-semibold text-white/40 uppercase tracking-wider mb-1.5">Insight</p>
          <span class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-white/50 cursor-not-allowed">Reports</span>
        </div>
      </nav>

      <div class="px-3 pb-4">
        <button @click="handleLogout" class="w-full text-left px-3 py-2.5 rounded-xl text-sm text-white/60 hover:bg-white/10 hover:text-white transition">
          Log out
        </button>
      </div>
    </aside>

    <main class="flex-1 min-w-0">
      <slot />
    </main>
  </div>
</template>
