import { ref } from 'vue'
import http from '../api/http'
import i18n from '../i18n'

// Falls back to the i18n default ("PEPY Assets") until /branding resolves,
// and again if the admin never set a custom system_name in Settings — so
// the sidebar/login screen/tab title never render blank.
const systemName = ref('')
const organizationName = ref('')
let loaded = false

async function loadBranding() {
  if (loaded) return
  loaded = true
  try {
    const { data } = await http.get('/branding')
    systemName.value = data.system_name || ''
    organizationName.value = data.organization_name || ''
    document.title = systemName.value || i18n.global.t('app_name')
  } catch (e) {
    // Public endpoint — a failure here just means the app keeps its default branding.
  }
}

export function useBranding() {
  return { systemName, organizationName, loadBranding }
}
