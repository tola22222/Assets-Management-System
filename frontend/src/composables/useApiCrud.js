import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../api/http'
import { useToastStore } from '../stores/toast'

export function useApiCrud(endpoint, { entityName = 'Item' } = {}) {
  const items = ref([])
  const loading = ref(false)
  const toast = useToastStore()
  const { t } = useI18n()

  async function fetchAll(params = {}) {
    loading.value = true
    try {
      const { data } = await http.get(endpoint, { params })
      items.value = data.data ?? data
    } finally {
      loading.value = false
    }
  }

  async function create(payload, config = {}) {
    const { data } = await http.post(endpoint, payload, config)
    toast.success(t('common.created_successfully', { entity: entityName }))
    await fetchAll()
    return data
  }

  async function update(id, payload, config = {}) {
    let data
    if (payload instanceof FormData) {
      payload.append('_method', 'PUT')
      ;({ data } = await http.post(`${endpoint}/${id}`, payload, config))
    } else {
      ;({ data } = await http.put(`${endpoint}/${id}`, payload, config))
    }
    toast.success(t('common.updated_successfully', { entity: entityName }))
    await fetchAll()
    return data
  }

  async function destroy(id) {
    await http.delete(`${endpoint}/${id}`)
    toast.success(t('common.deleted_successfully', { entity: entityName }))
    await fetchAll()
  }

  // Bulk counterpart to destroy() — fires the same per-row DELETE request for
  // each id, but refetches and toasts once at the end instead of per row.
  async function destroyMany(ids) {
    await Promise.all(ids.map((id) => http.delete(`${endpoint}/${id}`)))
    toast.success(t('common.deleted_successfully', { entity: entityName }))
    await fetchAll()
  }

  return { items, loading, fetchAll, create, update, destroy, destroyMany }
}
