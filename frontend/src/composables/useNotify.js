import { useNotificationStore } from '../stores/notification'

// Composition API usage: const { notify } = useNotify(); notify.success('Saved')
export function useNotify() {
  const store = useNotificationStore()
  return {
    notify: {
      success: store.success,
      error: store.error,
      warning: store.warning,
      info: store.info,
    },
  }
}

// Plain-object singleton for use outside component setup (axios interceptors,
// main.js globalProperties, plain JS modules).
export const notify = {
  success: (message, options) => useNotificationStore().success(message, options),
  error: (message, options) => useNotificationStore().error(message, options),
  warning: (message, options) => useNotificationStore().warning(message, options),
  info: (message, options) => useNotificationStore().info(message, options),
}
