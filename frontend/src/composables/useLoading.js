import { storeToRefs } from 'pinia'
import { useLoadingStore } from '../stores/loading'

// Composition API usage: const { active, show, hide } = useLoading()
export function useLoading() {
  const store = useLoadingStore()
  const { active, message } = storeToRefs(store)
  return { active, message, show: store.show, hide: store.hide, reset: store.reset }
}

// Plain-object singleton for use outside component setup (axios interceptors,
// main.js globalProperties). Each call resolves the store lazily so this module
// can be imported before Pinia is installed on the app.
export const loading = {
  show: (message) => useLoadingStore().show(message),
  hide: () => useLoadingStore().hide(),
  reset: () => useLoadingStore().reset(),
}
