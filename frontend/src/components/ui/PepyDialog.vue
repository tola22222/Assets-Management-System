<script setup>
import { useDisplay } from 'vuetify'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  maxWidth: { type: [String, Number], default: 560 },
  persistent: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  hideActions: { type: Boolean, default: false },
  confirmText: { type: String, default: '' },
  confirmColor: { type: String, default: 'primary' },
})
const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const { t } = useI18n()
const { mobile } = useDisplay()

function close() {
  emit('update:modelValue', false)
  emit('cancel')
}
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidth"
    :persistent="persistent || loading"
    :fullscreen="mobile"
    scrollable
    @update:model-value="(v) => !v && close()"
  >
    <v-card rounded="lg">
      <v-card-title class="d-flex align-center ga-3 pa-4">
        <v-avatar v-if="icon" size="40" rounded="lg" color="mint-tint">
          <v-icon :icon="icon" color="primary" />
        </v-avatar>
        <div class="flex-grow-1" style="min-width: 0">
          <div class="text-subtitle-1 font-weight-bold text-truncate">{{ title }}</div>
          <div v-if="subtitle" class="text-caption text-medium-emphasis text-truncate">{{ subtitle }}</div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" :disabled="loading" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-4">
        <slot />
      </v-card-text>

      <template v-if="!hideActions">
        <v-divider />
        <v-card-actions class="pa-4">
          <slot name="actions">
            <v-spacer />
            <v-btn variant="text" :disabled="loading" @click="close">{{ t('common.cancel') }}</v-btn>
            <v-btn :color="confirmColor" variant="flat" :loading="loading" @click="emit('confirm')">
              {{ confirmText || t('common.save') }}
            </v-btn>
          </slot>
        </v-card-actions>
      </template>
    </v-card>
  </v-dialog>
</template>
