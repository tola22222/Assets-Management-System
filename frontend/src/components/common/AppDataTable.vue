<script setup>
import { computed, useSlots } from 'vue'

const props = defineProps({
  headers: { type: Array, required: true },
  items: { type: Array, required: true },
  // Total row count on the server, for server-side pagination. -1 disables the count display.
  itemsLength: { type: Number, default: -1 },
  loading: { type: Boolean, default: false },
  search: { type: String, default: '' },
  searchable: { type: Boolean, default: true },
  searchLabel: { type: String, default: 'Search' },
  page: { type: Number, default: 1 },
  itemsPerPage: { type: Number, default: 10 },
  itemsPerPageOptions: {
    type: Array,
    default: () => [
      { value: 10, title: '10' },
      { value: 25, title: '25' },
      { value: 50, title: '50' },
      { value: -1, title: 'All' },
    ],
  },
  sortBy: { type: Array, default: () => [] },
  emptyText: { type: String, default: 'No data available' },
  // Built-in row actions column (header with key "actions"). Set to false to
  // fully own it via the #item.actions slot instead.
  showView: { type: Boolean, default: true },
  showEdit: { type: Boolean, default: true },
  showDelete: { type: Boolean, default: true },
  // Row-selection checkbox column, for bulk actions (see the #bulk-actions slot).
  showSelect: { type: Boolean, default: false },
  itemValue: { type: String, default: 'id' },
  selected: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:options',
  'update:search',
  'update:page',
  'update:itemsPerPage',
  'update:sortBy',
  'update:selected',
  'view',
  'edit',
  'delete',
])

const slots = useSlots()

// Passes through any custom column/template slot (e.g. #item.status) to
// v-data-table-server untouched. Slots with built-in defaults are handled by
// their own <template> below instead, so they aren't double-registered.
const OWN_SLOTS = new Set(['title', 'toolbar-end', 'filters', 'bulk-actions', 'item.actions', 'loading', 'no-data'])
const forwardedSlotNames = computed(() => Object.keys(slots).filter((name) => !OWN_SLOTS.has(name)))

const selectedProxy = computed({
  get: () => props.selected,
  set: (value) => emit('update:selected', value),
})

function onUpdateOptions(options) {
  emit('update:options', options)
  emit('update:page', options.page)
  emit('update:itemsPerPage', options.itemsPerPage)
  emit('update:sortBy', options.sortBy)
}

function onSearch(value) {
  emit('update:search', value)
}
</script>

<template>
  <v-card rounded="lg" variant="flat" border>
    <v-card-title v-if="$slots.title || searchable || $slots['toolbar-end']" class="d-flex flex-wrap align-center ga-3 pa-4">
      <slot name="title" />
      <v-spacer />
      <v-text-field
        v-if="searchable"
        :model-value="search"
        density="compact"
        variant="outlined"
        hide-details
        clearable
        prepend-inner-icon="mdi-magnify"
        :label="searchLabel"
        style="max-width: 320px"
        @update:model-value="onSearch"
      />
      <slot name="toolbar-end" />
    </v-card-title>

    <div v-if="$slots.filters" class="d-flex flex-wrap align-center ga-3 px-4 pb-4">
      <slot name="filters" />
    </div>

    <div
      v-if="showSelect && selected.length"
      class="d-flex flex-wrap align-center ga-3 px-4 py-2 bg-brand-50 dark:bg-brand-900/30 border-b border-line"
    >
      <slot name="bulk-actions" :selected="selected" :clear="() => emit('update:selected', [])" />
    </div>

    <v-data-table-server
      :headers="headers"
      :items="items"
      :items-length="itemsLength"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :items-per-page-options="itemsPerPageOptions"
      :show-select="showSelect"
      :item-value="itemValue"
      v-model="selectedProxy"
      @update:options="onUpdateOptions"
    >
      <template v-for="slotName in forwardedSlotNames" #[slotName]="slotProps" :key="slotName">
        <slot :name="slotName" v-bind="slotProps" />
      </template>

      <template #item.actions="{ item }">
        <slot name="item.actions" :item="item">
          <div class="d-flex ga-1">
            <v-btn
              v-if="showView"
              icon="mdi-eye-outline"
              size="small"
              variant="text"
              color="info"
              @click="emit('view', item)"
            />
            <v-btn
              v-if="showEdit"
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="primary"
              @click="emit('edit', item)"
            />
            <v-btn
              v-if="showDelete"
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="emit('delete', item)"
            />
          </div>
        </slot>
      </template>

      <template #loading>
        <slot name="loading">
          <v-skeleton-loader type="table-row@6" />
        </slot>
      </template>

      <template #no-data>
        <slot name="no-data">
          <div class="text-center py-8 text-medium-emphasis">
            <v-icon icon="mdi-database-off-outline" size="40" class="mb-2" />
            <div>{{ emptyText }}</div>
          </div>
        </slot>
      </template>
    </v-data-table-server>
  </v-card>
</template>
