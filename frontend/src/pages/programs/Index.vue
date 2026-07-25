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
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: programs, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/programs')
const { create, update, destroy } = useApiCrud('/programs', { entityName: t('programs.entity'), refetch: fetchPage })
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
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

async function confirmDelete() {
  try {
    await destroy(deletingId.value)
  } catch (e) {
    toast.error(e.response?.data?.message || t('programs.delete_failed'))
  } finally {
    deletingId.value = null
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
  <AppLayout>
    <div class="p-8 max-w-4xl mx-auto space-y-6">
      <PageHeader :title="t('programs.title')" :subtitle="t('programs.subtitle')" :buttonText="t('programs.new')" @action="openCreate" />

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
        @delete="(row) => (deletingId = row.id)"
      >
        <template #item.description="{ item }">{{ item.description || '—' }}</template>
      </AppDataTable>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('programs.edit_title') : t('programs.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('programs.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.description') }}</label>
            <textarea v-model="form.description" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('programs.save_changes') : t('programs.create_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
