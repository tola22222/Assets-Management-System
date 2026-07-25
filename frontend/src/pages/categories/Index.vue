<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'

const { t } = useI18n()
const {
  items: categories, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/categories', { defaultSort: 'name', defaultDir: 'asc' })
const { create, update, destroy } = useApiCrud('/categories', { entityName: t('categories.entity'), refetch: fetchPage })
const { confirm } = useConfirm()

const CATEGORY_CODES = ['MOV', 'FAF', 'COM', 'EQU']

const showModal = ref(false)
const editingId = ref(null)
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
  { title: t('categories.short_name'), key: 'short_name', sortable: true },
  { title: t('categories.assets_count'), key: 'assets_count', sortable: false, align: 'end' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 110 },
])

onMounted(fetchPage)
</script>

<template>
    <v-container class="d-flex flex-column ga-6">
      <AppPageHeader
        :title="t('categories.title')"
        :subtitle="t('categories.subtitle')"
        :actions="[{ label: t('categories.new'), icon: 'mdi-plus', onClick: openCreate }]"
      />

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
        @delete="handleDelete"
      >
        <template #item.short_name="{ item }">{{ item.short_name || '—' }}</template>
        <template #item.assets_count="{ item }">{{ item.assets_count ?? 0 }}</template>
      </AppDataTable>
    </v-container>

    <Modal v-if="showModal" :title="editingId ? t('categories.edit_title') : t('categories.create_title')" @close="showModal = false">
      <v-form @submit.prevent="handleSubmit">
        <v-card-text class="d-flex flex-column ga-1">
          <v-text-field v-model="form.name" :label="t('categories.name_required')" required />
          <v-combobox
            v-model="form.short_name"
            :items="CATEGORY_CODES"
            :label="t('categories.short_name')"
            maxlength="6"
            :hint="`2-6 letters/numbers. MOV/FAF/COM/EQU are the Manual's defaults — you can also type your own.`"
            persistent-hint
            @update:model-value="(v) => (form.short_name = (v || '').toUpperCase())"
          />
          <v-textarea v-model="form.description" :label="t('common.description')" rows="2" class="mt-2" />
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">
            {{ editingId ? t('categories.save_changes') : t('categories.create_button') }}
          </v-btn>
          <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
        </v-card-actions>
      </v-form>
    </Modal>
</template>
