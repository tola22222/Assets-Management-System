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
  items: categories, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/categories', { defaultSort: 'name', defaultDir: 'asc' })
const { create, update, destroy } = useApiCrud('/categories', { entityName: t('categories.entity'), refetch: fetchPage })

const CATEGORY_CODES = ['MOV', 'FAF', 'COM', 'EQU']

function uppercaseShortName(e) {
  form.short_name = e.target.value.toUpperCase()
}

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', short_name: '', description: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', short_name: '', description: '' })
  showModal.value = true
}

function openEdit(category) {
  editingId.value = category.id
  Object.assign(form, { name: category.name, short_name: category.short_name || '', description: category.description || '' })
  showModal.value = true
}

async function handleSubmit() {
  if (editingId.value) {
    await update(editingId.value, form)
  } else {
    await create(form)
  }
  showModal.value = false
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

const headers = computed(() => [
  { title: t('common.name'), key: 'name', sortable: true },
  { title: t('categories.short_name'), key: 'short_name', sortable: true },
  { title: t('categories.assets_count'), key: 'assets_count', sortable: false, align: 'end' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 110 },
])

onMounted(fetchPage)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader :title="t('categories.title')" :subtitle="t('categories.subtitle')" :buttonText="t('categories.new')" @action="openCreate" />

      <AppDataTable
        :headers="headers"
        :items="categories"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :search-label="t('categories.search_placeholder')"
        :empty-text="search ? t('categories.empty_search') : t('categories.empty')"
        @update:search="setSearch"
        @update:options="handleOptions"
        @edit="openEdit"
        @delete="(row) => (deletingId = row.id)"
      >
        <template #item.short_name="{ item }">{{ item.short_name || '—' }}</template>
        <template #item.assets_count="{ item }">{{ item.assets_count ?? 0 }}</template>
      </AppDataTable>
    </div>

    <Modal v-if="showModal" :title="editingId ? t('categories.edit_title') : t('categories.create_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('categories.name_required') }}</label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('categories.short_name') }}</label>
            <input :value="form.short_name" @input="uppercaseShortName" list="category-codes" maxlength="6"
              placeholder="e.g. MOV, or your own like ELEC" class="input" />
            <datalist id="category-codes">
              <option v-for="code in CATEGORY_CODES" :key="code" :value="code" />
            </datalist>
            <p class="text-xs text-faint">2-6 letters/numbers. MOV/FAF/COM/EQU are the Manual's defaults — you can also type your own.</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.description') }}</label>
            <textarea v-model="form.description" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ editingId ? t('categories.save_changes') : t('categories.create_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
