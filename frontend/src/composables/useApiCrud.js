import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../api/http'
import { useToastStore } from '../stores/toast'

/**
 * Shared REST helper for the index pages.
 *
 * Every operation reports its own failure. Previously only the success path
 * toasted, so a rejected request produced nothing at all — a delete that the
 * server refused looked exactly like a click that never landed, and the row
 * stayed on screen with no explanation.
 *
 * Failures toast here and then re-throw, so the caller can still do its own
 * cleanup (closing a dialog, clearing a selection) in a catch/finally without
 * having to work out what the message should say. Callers must NOT toast again.
 */
export function useApiCrud(endpoint, { entityName = 'Item' } = {}) {
  const items = ref([])
  const loading = ref(false)
  const toast = useToastStore()
  const { t } = useI18n()

  function report(error, fallback) {
    toast.error(errorMessage(error, fallback))
  }

  async function fetchAll(params = {}) {
    loading.value = true
    try {
      const { data } = await http.get(endpoint, { params })
      items.value = data.data ?? data
    } catch (e) {
      // An empty grid after a failed load reads as "you have no records",
      // which is a different and much more alarming thing than "this didn't load".
      items.value = []
      report(e, t('errors.load_failed', { entity: entityName }))
      throw e
    } finally {
      loading.value = false
    }
  }

  async function create(payload, config = {}) {
    try {
      const { data } = await http.post(endpoint, payload, config)
      toast.success(t('common.created_successfully', { entity: entityName }))
      await fetchAll()
      return data
    } catch (e) {
      report(e)
      throw e
    }
  }

  async function update(id, payload, config = {}) {
    try {
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
    } catch (e) {
      report(e)
      throw e
    }
  }

  async function destroy(id) {
    try {
      await http.delete(`${endpoint}/${id}`)
      toast.success(t('common.deleted_successfully', { entity: entityName }))
      await fetchAll()
    } catch (e) {
      report(e)
      throw e
    }
  }

  /**
   * Bulk counterpart to destroy(). Uses allSettled rather than all() so one
   * refused row no longer aborts the rest and leaves the user guessing which
   * ones actually went: the partial outcome is reported honestly, and the list
   * is refetched either way so what is on screen matches the server.
   */
  async function destroyMany(ids) {
    const results = await Promise.allSettled(ids.map((id) => http.delete(`${endpoint}/${id}`)))
    const rejected = results.filter((r) => r.status === 'rejected')
    const deleted = ids.length - rejected.length

    if (deleted > 0) {
      toast.success(t('common.deleted_successfully', { entity: entityName }))
    }
    if (rejected.length > 0) {
      toast.error(errorMessage(rejected[0].reason, t('common.bulk_delete_failed', { count: rejected.length })))
    }

    await fetchAll().catch(() => {})
  }

  return { items, loading, fetchAll, create, update, destroy, destroyMany }
}
