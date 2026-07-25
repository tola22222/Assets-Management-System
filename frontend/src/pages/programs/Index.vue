<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: programs, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/programs')
const { create, update, destroy } = useApiCrud('/programs', { entityName: t('programs.entity'), refetch: fetchPage })
const { confirm } = useConfirm()
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const form = reactive({ name: '', description: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', description: '' })
  showModal.value = true
}

function openEdit(program) {
  editingId.value = program.id
  Object.assign(form, { name: program.name, description: program.description || '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) await update(editingId.value, form)
    else await create(form)
    showModal.value = false
  } catch (e) {
    toast.error(e.response?.data?.message || t('programs.save_failed'))
  }
}

async function handleDelete(row) {
  const ok = await confirm({
    title: t('confirm.delete_title'),
    message: t('confirm.delete_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  try {
    await destroy(row.id)
  } catch (e) {
    toast.error(e.response?.data?.message || t('programs.delete_failed'))
  }
}

const headers = computed(() => [
  { title: t('common.name'), key: 'name', sortable: true },
  { title: t('common.description'), key: 'description', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 110 },
])

onMounted(fetchPage)
</script>

<template>
  <v-container class="d-flex flex-column ga-6">
    <AppPageHeader
      :title="t('programs.title')"
      :subtitle="t('programs.subtitle')"
      :actions="[{ label: t('programs.new'), icon: 'mdi-plus', onClick: openCreate }]"
    />

    <AppDataTable
      :headers="headers"
      :items="programs"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="sortByVuetify"
      :search="search"
      :search-label="t('programs.search_placeholder')"
      :empty-text="search ? t('programs.empty_search') : t('programs.empty')"
      @update:search="setSearch"
      @update:options="handleOptions"
      @edit="openEdit"
      @delete="handleDelete"
    >
      <template #item.description="{ item }">{{ item.description || '—' }}</template>
    </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="editingId ? t('programs.edit_title') : t('programs.create_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-text-field v-model="form.name" :label="t('programs.name_required')" required />
        <v-textarea v-model="form.description" :label="t('common.description')" rows="2" />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">
          {{ editingId ? t('programs.save_changes') : t('programs.create_button') }}
        </v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
