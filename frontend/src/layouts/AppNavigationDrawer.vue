<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useAppNav } from '../composables/useAppNav'
import logoUrl from '../assets/logo/Official PEPY Logo_Green.png'

const props = defineProps({
  permanent: { type: Boolean, default: true },
  temporary: { type: Boolean, default: false },
  rail: { type: Boolean, default: false },
})
const emit = defineEmits(['toggle-rail'])
const model = defineModel({ type: Boolean, default: true })

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const { ICONS, navSections } = useAppNav()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

function closeOnMobile() {
  if (props.temporary) model.value = false
}

function initials(name) {
  if (!name) return 'U'
  return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase()
}
</script>

<template>
  <v-navigation-drawer
    v-model="model"
    :permanent="permanent"
    :temporary="temporary"
    :rail="rail"
    rail-width="72"
    theme="pepyDark"
    color="primary"
    width="256"
    class="pepy-nav-drawer"
  >
    <div
      class="d-flex align-center ga-3 pa-4"
      :class="{ 'justify-center': rail, 'pepy-nav-header-rail': rail }"
      style="border-bottom: 1px solid rgba(255, 255, 255, 0.1)"
      @click="rail && emit('toggle-rail')"
    >
      <v-avatar size="36" rounded="0">
        <v-img :src="logoUrl" alt="PEPY" />
      </v-avatar>
      <div v-if="!rail" class="flex-grow-1" style="min-width: 0">
        <p class="font-weight-bold text-truncate mb-0">{{ t('app_name') }}</p>
        <p class="text-caption text-truncate mb-0" style="opacity: 0.6">{{ t('app_subtitle') }} · {{ t('app_location') }}</p>
      </div>
      <v-btn
        v-if="permanent && !rail"
        icon="mdi-chevron-left"
        size="small"
        variant="text"
        density="comfortable"
        :title="t('nav.hide_sidebar')"
        @click="emit('toggle-rail')"
      />
    </div>

    <v-list density="compact" nav>
      <template v-for="section in navSections" :key="section.key">
        <v-list-subheader v-if="!rail">{{ section.title }}</v-list-subheader>

        <v-tooltip
          v-for="item in section.items"
          :key="item.to"
          :disabled="!rail"
          location="end"
          :text="item.label"
        >
          <template #activator="{ props: tip }">
            <v-list-item
              v-bind="tip"
              :to="item.to"
              :exact="item.exact"
              :prepend-icon="item.icon"
              :title="item.label"
              rounded="pill"
              class="mx-2 pepy-nav-item"
              @click="closeOnMobile"
            />
          </template>
        </v-tooltip>
      </template>
    </v-list>

    <template #append>
      <v-divider />
      <div class="pa-2">
        <v-menu location="top">
          <template #activator="{ props: menuProps }">
            <div
              v-bind="menuProps"
              class="d-flex align-center ga-2 pa-2 rounded-lg pepy-nav-profile"
              :class="{ 'justify-center': rail }"
            >
              <v-avatar size="36" color="secondary">
                <v-img v-if="auth.user?.photo_url" :src="auth.user.photo_url" alt="" />
                <span v-else class="text-caption font-weight-bold">{{ initials(auth.user?.name) }}</span>
              </v-avatar>
              <div v-if="!rail" class="flex-grow-1 text-truncate" style="min-width: 0">
                <div class="text-body-2 font-weight-medium text-truncate" style="color: #fff">{{ auth.user?.name }}</div>
                <div class="text-caption text-truncate text-capitalize" style="opacity: 0.65">{{ auth.user?.role?.replace('_', ' ') }}</div>
              </div>
              <v-icon v-if="!rail" icon="mdi-dots-vertical" size="18" style="opacity: 0.7" />
            </div>
          </template>
          <v-list density="compact">
            <v-list-item to="/profile" :prepend-icon="ICONS.profile" :title="t('header.profile')" />
            <v-list-item to="/settings" :prepend-icon="ICONS.cog" :title="t('header.settings')" />
            <v-divider class="my-1" />
            <v-list-item :prepend-icon="ICONS.logout" :title="t('nav.logout')" class="text-error" @click="handleLogout" />
          </v-list>
        </v-menu>
      </div>
    </template>
  </v-navigation-drawer>
</template>

<style scoped>
/* Vuetify's default active-item style is a full-width tinted block; the PEPY
   design wants a floating cream "pill" instead, so it's overridden directly
   rather than via the theme's list-item color prop (which would resolve
   against this drawer's forced dark theme, not the light cream surface). */
.pepy-nav-item:deep(.v-list-item__overlay) {
  opacity: 0;
}
.pepy-nav-item.v-list-item--active {
  background: #fbf8f1;
  color: #163832;
}
.pepy-nav-item.v-list-item--active :deep(.v-icon) {
  color: #163832;
}
.pepy-nav-profile:hover {
  background: rgba(255, 255, 255, 0.08);
  cursor: pointer;
}
.pepy-nav-header-rail {
  cursor: pointer;
}
.pepy-nav-header-rail:hover {
  background: rgba(255, 255, 255, 0.06);
}
</style>
