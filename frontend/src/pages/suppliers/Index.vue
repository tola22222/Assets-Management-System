<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'

const { t } = useI18n()
const {
  items: suppliers, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/suppliers')
const { create, update, destroy } = useApiCrud('/suppliers', { entityName: t('suppliers.entity'), refetch: fetchPage })
const { confirm } = useConfirm()

const showModal = ref(false)
const editingId = ref(null)
const form = reactive({ name: '', phone: '', address: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', phone: '', address: '' })
  showModal.value = true
}

function openEdit(supplier) {
  editingId.value = supplier.id
  Object.assign(form, { name: supplier.name, phone: supplier.phone || '', address: supplier.address || '' })
  showModal.value = true
}

async function handleSubmit() {
  if (editingId.value) await update(editingId.value, form)
  else await create(form)
  showModal.value = false
}

async function handleDelete(row) {
  const ok = await confirm({
    title: t('confirm.delete_title'),
    message: t('confirm.delete_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (ok) await destroy(row.id)
}

const headers = computed(() => [
  { title: t('common.name'), key: 'name', sortable: true },
  { title: t('common.phone'), key: 'phone', sortable: false },
  { title: t('common.address'), key: 'address', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 110 },
])

onMounted(fetchPage)
</script>

<template>
  <v-container class="d-flex flex-column ga-6">
    <AppPageHeader
      :title="t('suppliers.title')"
      :subtitle="t('suppliers.subtitle')"
      :actions="[{ label: t('suppliers.new'), icon: 'mdi-plus', onClick: openCreate }]"
    />

    <AppDataTable
      :headers="headers"
      :items="suppliers"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="sortByVuetify"
      :search="search"
      :search-label="t('suppliers.search_placeholder')"
      :empty-text="search ? t('suppliers.empty_search') : t('suppliers.empty')"
      @update:search="setSearch"
      @update:options="handleOptions"
      @edit="openEdit"
      @delete="handleDelete"
    >
      <template #item.phone="{ item }">{{ item.phone || '—' }}</template>
      <template #item.address="{ item }">{{ item.address || '—' }}</template>
    </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="editingId ? t('suppliers.edit_title') : t('suppliers.create_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-text-field v-model="form.name" :label="t('suppliers.name_required')" required />
        <v-text-field v-model="form.phone" :label="t('common.phone')" />
        <v-textarea v-model="form.address" :label="t('common.address')" rows="2" />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">
          {{ editingId ? t('suppliers.save_changes') : t('suppliers.create_button') }}
        </v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
