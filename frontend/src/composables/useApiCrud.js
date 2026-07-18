import { ref } from 'vue'
import http from '../api/http'
import { useToastStore } from '../stores/toast'

export function useApiCrud(endpoint, { entityName = 'Item' } = {}) {
  const items = ref([])
  const loading = ref(false)
  const toast = useToastStore()

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
    toast.success(`${entityName} created successfully.`)
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
    toast.success(`${entityName} updated successfully.`)
    await fetchAll()
    return data
  }

  async function destroy(id) {
    await http.delete(`${endpoint}/${id}`)
    toast.success(`${entityName} deleted successfully.`)
    await fetchAll()
  }

  return { items, loading, fetchAll, create, update, destroy }
}
