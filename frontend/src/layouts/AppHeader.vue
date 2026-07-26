<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useAppNav } from '../composables/useAppNav'
import NotificationBell from '../components/ui/NotificationBell.vue'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import LanguageSwitcher from '../components/ui/LanguageSwitcher.vue'
import GlobalSearchField from '../components/ui/GlobalSearchField.vue'
import Breadcrumbs from '../components/ui/Breadcrumbs.vue'

defineProps({
  collapsed: { type: Boolean, default: false },
})
const emit = defineEmits(['toggle-sidebar'])

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const { breadcrumb, ICONS } = useAppNav()

function initials(name) {
  if (!name) return 'U'
  return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase()
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <v-app-bar flat density="comfortable" color="surface">
    <v-app-bar-nav-icon :title="collapsed ? t('nav.show_sidebar') : t('nav.hide_sidebar')" @click="emit('toggle-sidebar')" />

    <Breadcrumbs :section="breadcrumb?.section" :page="breadcrumb?.page" />

    <v-spacer />

    <div class="d-none d-md-block mr-2" style="width: 20rem">
      <GlobalSearchField />
    </div>

    <LanguageSwitcher />
    <ThemeToggle />
    <NotificationBell />

    <v-menu>
      <template #activator="{ props }">
        <v-btn v-bind="props" variant="text" class="text-none ml-1">
          <v-avatar size="32" color="primary">
            <v-img v-if="auth.user?.photo_url" :src="auth.user.photo_url" alt="" />
            <span v-else class="text-caption font-weight-bold">{{ initials(auth.user?.name) }}</span>
          </v-avatar>
          <div class="d-none d-sm-block text-left ml-2">
            <div class="text-body-2 font-weight-medium text-truncate" style="max-width: 10rem">{{ auth.user?.name }}</div>
            <div class="text-caption text-medium-emphasis text-capitalize">{{ auth.user?.role?.replace('_', ' ') }}</div>
          </div>
          <v-icon icon="mdi-chevron-down" size="18" class="ml-1" />
        </v-btn>
      </template>
      <v-list density="compact" min-width="180">
        <v-list-item to="/profile" :prepend-icon="ICONS.profile" :title="t('header.profile')" />
        <v-list-item to="/settings" :prepend-icon="ICONS.cog" :title="t('header.settings')" />
        <v-divider class="my-1" />
        <v-list-item :prepend-icon="ICONS.logout" :title="t('header.logout')" class="text-error" @click="handleLogout" />
      </v-list>
    </v-menu>
  </v-app-bar>
</template>
