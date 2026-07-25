<script setup>
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useAppNav } from '../composables/useAppNav'
import NotificationBell from '../components/ui/NotificationBell.vue'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import Breadcrumbs from '../components/ui/Breadcrumbs.vue'

defineProps({
  collapsed: { type: Boolean, default: false },
})
const emit = defineEmits(['toggle-sidebar'])

const { t } = useI18n()
const auth = useAuthStore()
const { breadcrumb } = useAppNav()

function initials(name) {
  if (!name) return 'U'
  return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase()
}
</script>

<template>
  <v-app-bar flat density="comfortable" color="surface">
    <v-app-bar-nav-icon :title="collapsed ? t('nav.show_sidebar') : t('nav.hide_sidebar')" @click="emit('toggle-sidebar')" />

    <Breadcrumbs :section="breadcrumb?.section" :page="breadcrumb?.page" />
    <v-spacer />

    <ThemeToggle />
    <NotificationBell />

    <v-btn to="/profile" variant="text" class="text-none ml-1">
      <v-avatar size="32" color="primary" class="mr-2">
        <v-img v-if="auth.user?.photo_url" :src="auth.user.photo_url" alt="" />
        <span v-else class="text-caption font-weight-bold">{{ initials(auth.user?.name) }}</span>
      </v-avatar>
      <div class="d-none d-sm-block text-left">
        <div class="text-body-2 font-weight-medium text-truncate" style="max-width: 10rem">{{ auth.user?.name }}</div>
        <div class="text-caption text-medium-emphasis text-capitalize">{{ auth.user?.role?.replace('_', ' ') }}</div>
      </div>
    </v-btn>
  </v-app-bar>
</template>
