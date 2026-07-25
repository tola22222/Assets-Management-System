<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  columns: { type: Array, required: true }, // [{ key, label }]
  isVisible: { type: Function, required: true },
})
const emit = defineEmits(['toggle', 'reset'])
</script>

<template>
  <v-menu :close-on-content-click="false">
    <template #activator="{ props: menuProps }">
      <v-btn variant="outlined" size="small" prepend-icon="mdi-view-column-outline" v-bind="menuProps">
        {{ t('common.columns') }}
      </v-btn>
    </template>
    <v-list density="compact" min-width="220">
      <v-list-subheader>{{ t('common.toggle_columns') }}</v-list-subheader>
      <v-list-item v-for="col in columns" :key="col.key" @click="emit('toggle', col.key)">
        <template #prepend>
          <v-checkbox-btn :model-value="isVisible(col.key)" @click.stop="emit('toggle', col.key)" />
        </template>
        <v-list-item-title>{{ col.label }}</v-list-item-title>
      </v-list-item>
      <v-divider class="my-1" />
      <v-list-item @click="emit('reset')">
        <v-list-item-title class="text-medium-emphasis">{{ t('common.reset_columns') }}</v-list-item-title>
      </v-list-item>
    </v-list>
  </v-menu>
</template>
