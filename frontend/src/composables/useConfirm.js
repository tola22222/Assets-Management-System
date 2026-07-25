import { useConfirmStore } from '../stores/confirm'

// Composition API usage:
//   const { confirm } = useConfirm()
//   const ok = await confirm({ title: 'Delete Customer', message: '...', color: 'error' })
export function useConfirm() {
  const store = useConfirmStore()
  return { confirm: (options) => store.open(options) }
}

// Plain-function singleton for use outside component setup.
export const confirm = (options) => useConfirmStore().open(options)
