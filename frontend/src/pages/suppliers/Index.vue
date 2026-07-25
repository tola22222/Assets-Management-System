<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'

const { t } = useI18n()
const {
  items: suppliers, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/suppliers')
const { create, update, destroy } = useApiCrud('/suppliers', { entityName: t('suppliers.entity'), refetch: fetchPage })

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
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

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
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
  <AppLayout>
    <div class="p-8 max-w-4xl mx-auto space-y-6">
      <PageHeader :title="t('suppliers.title')" :subtitle="t('suppliers.subtitle')" :buttonText="t('suppliers.new')" @action="openCreate" />

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
        @delete="(row) => (deletingId = row.id)"
      >
        <template #item.phone="{ item }">{{ item.phone || '—' }}</template>
        <template #item.address="{ item }">{{ item.address || '—' }}</template>
      </AppDataTable>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('suppliers.edit_title') : t('suppliers.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('suppliers.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.phone') }}</label>
            <input v-model="form.phone" class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.address') }}</label>
            <textarea v-model="form.address" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('suppliers.save_changes') : t('suppliers.create_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
