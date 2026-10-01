import { ref, computed } from 'vue'
import http from '../api/http'
import { useAuthStore } from '../stores/auth'

/**
 * The signed-in account's effective permissions, loaded once per session.
 *
 * This is presentation only. Every route is guarded server side, and hiding a
 * control here just avoids offering an action that would come back 403 — it is
 * never the thing that stops it. Anything security-relevant must be enforced in
 * the controller or route middleware, not here.
 */
const permissions = ref({})
const hiddenModules = ref([])
// Only the custom-role grants (Roles & Permissions), without the base role's
// baseline — what allows() below adds on top of the base-role list.
const customPermissions = ref({})
const loaded = ref(false)
const loading = ref(false)

export async function loadPermissions(force = false) {
  if (loading.value || (loaded.value && !force)) return
  if (!localStorage.getItem('token')) return

  loading.value = true
  try {
    const { data } = await http.get('/me/permissions')
    permissions.value = data.permissions || {}
    hiddenModules.value = data.hidden_modules || []
    customPermissions.value = data.custom_permissions || {}
    loaded.value = true
  } catch {
    // Fall back to showing nothing extra rather than guessing. The server is
    // still the authority, so a failed load degrades the menu, not security.
    permissions.value = {}
    hiddenModules.value = []
    customPermissions.value = {}
  } finally {
    loading.value = false
  }
}

/**
 * Does one of the signed-in account's custom roles grant module.ability?
 * For the router guard, which runs outside components. Loads the permission
 * payload first if it has not arrived yet.
 */
export async function hasCustomGrant(module, ability = 'view') {
  if (!module) return false
  await loadPermissions()
  return (customPermissions.value[module] || []).includes(ability)
}

export function clearPermissions() {
  permissions.value = {}
  hiddenModules.value = []
  customPermissions.value = {}
  loaded.value = false
}

export function usePermissions() {
  const can = (module, ability = 'view') => {
    // Until the payload arrives, allow — the previous behaviour showed
    // everything and let the server refuse. Flipping to deny-by-default here
    // would blank the whole menu for a moment on every page load.
    if (!loaded.value) return true
    return (permissions.value[module] || []).includes(ability)
  }

  const canAny = (module, abilities) => abilities.some((a) => can(module, a))

  // The server's role: route guard, mirrored: one of these base roles, OR a
  // custom role granting module.ability. Use it for any button whose route is
  // role-guarded, so a custom role shows exactly the buttons that will work.
  const auth = useAuthStore()
  const allows = (roles, module, ability) =>
    roles.includes(auth.user?.role) || (customPermissions.value[module] || []).includes(ability)
  const isHidden = (module) => hiddenModules.value.includes(module)

  /** True when the module should appear in navigation at all. */
  const canSee = (module) => can(module, 'view') && !isHidden(module)

  return {
    permissions: computed(() => permissions.value),
    hiddenModules: computed(() => hiddenModules.value),
    loaded: computed(() => loaded.value),
    can,
    canAny,
    allows,
    canSee,
    isHidden,
    loadPermissions,
    clearPermissions,
  }
}
