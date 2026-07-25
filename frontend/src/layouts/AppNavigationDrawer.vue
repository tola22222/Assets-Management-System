<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useAppNav } from '../composables/useAppNav'
import logoUrl from '../assets/logo/Official PEPY Logo_Green.png'

defineProps({
  permanent: { type: Boolean, default: true },
  temporary: { type: Boolean, default: false },
})
const model = defineModel({ type: Boolean, default: true })

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const { ICONS, isAdmin, topLinks, myAssets, mainGroups, settingGroup, activeGroupKey } = useAppNav()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

// Single-open accordion that follows the active route.
const openGroups = ref(activeGroupKey.value ? [activeGroupKey.value] : [])
watch(activeGroupKey, (k) => { if (k && !openGroups.value.includes(k)) openGroups.value = [k] }, { immediate: true })

function closeOnMobile() {
  model.value = false
}
</script>

<template>
  <v-navigation-drawer v-model="model" :permanent="permanent" :temporary="temporary" theme="dark" color="primary-darken-1" width="256">
    <div class="d-flex align-center ga-3 pa-4" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1)">
      <v-avatar size="36" rounded="0">
        <v-img :src="logoUrl" alt="PEPY" />
      </v-avatar>
      <div class="flex-grow-1" style="min-width: 0">
        <p class="font-weight-bold text-truncate mb-0">{{ t('app_name') }}</p>
        <p class="text-caption text-truncate mb-0" style="opacity: 0.6">{{ t('app_subtitle') }} · {{ t('app_location') }}</p>
      </div>
    </div>

    <v-list v-model:opened="openGroups" density="compact" nav>
      <v-list-item
        v-for="item in topLinks"
        :key="item.to"
        :to="item.to"
        :exact="item.exact"
        :prepend-icon="item.icon"
        :title="item.label"
        @click="temporary && closeOnMobile()"
      />

      <template v-if="!isAdmin">
        <v-list-subheader>{{ t('nav.my_assets') }}</v-list-subheader>
        <v-list-item
          v-for="item in myAssets"
          :key="item.to"
          :to="item.to"
          :title="item.label"
          class="pl-8"
          @click="temporary && closeOnMobile()"
        />
      </template>

      <v-list-group v-for="group in mainGroups" :key="group.key" :value="group.key">
        <template #activator="{ props: activatorProps }">
          <v-list-item v-bind="activatorProps" :prepend-icon="group.icon" :title="group.title" />
        </template>
        <v-list-item
          v-for="item in group.items"
          :key="item.to"
          :to="item.to"
          :title="item.label"
          @click="temporary && closeOnMobile()"
        />
      </v-list-group>

      <v-list-item to="/reports" :prepend-icon="ICONS.chart" :title="t('nav.reports')" @click="temporary && closeOnMobile()" />
    </v-list>

    
      <v-list v-if="isAdmin" v-model:opened="openGroups" density="compact" nav>
        <v-list-group :value="settingGroup.key">
          <template #activator="{ props: activatorProps }">
            <v-list-item v-bind="activatorProps" :prepend-icon="settingGroup.icon" :title="settingGroup.title" />
          </template>
          <v-list-item
            v-for="item in settingGroup.items"
            :key="item.to"
            :to="item.to"
            :title="item.label"
            @click="temporary && closeOnMobile()"
          />
        </v-list-group>
      </v-list>
      <template #append>
        <v-divider />
        <v-list density="compact" nav>
          <v-list-item :prepend-icon="ICONS.logout" :title="t('nav.logout')" class="text-red-lighten-2" @click="handleLogout" />
        </v-list>
      </template>
  </v-navigation-drawer>
</template>
