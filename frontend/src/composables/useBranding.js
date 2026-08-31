import { ref } from 'vue'
import http from '../api/http'
import i18n from '../i18n'

// Falls back to the i18n default ("PEPY Assets") until /branding resolves,
// and again if the admin never set a custom system_name in Settings — so
// the sidebar/login screen/tab title never render blank.
const systemName = ref('')
const organizationName = ref('')
// Empty until an admin uploads one; the layouts fall back to the bundled PEPY
// mark, so this staying empty is the normal case, not an error.
const logoUrl = ref('')
let loaded = false

async function loadBranding() {
  if (loaded) return
  loaded = true
  try {
    const { data } = await http.get('/branding')
    systemName.value = data.system_name || ''
    organizationName.value = data.organization_name || ''
    logoUrl.value = data.logo_url || ''
    document.title = systemName.value || i18n.global.t('app_name')
  } catch (e) {
    // Public endpoint — a failure here just means the app keeps its default branding.
  }
}

// loadBranding() is a once-per-session cache; after an admin saves a new logo
// or org name we need to re-read it so the sidebar updates without a reload.
async function refreshBranding() {
  loaded = false
  await loadBranding()
}

export function useBranding() {
  return { systemName, organizationName, logoUrl, loadBranding, refreshBranding }
}
