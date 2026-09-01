import axios from 'axios'
import router from '../router'
import i18n from '../i18n'

const http = axios.create({
  baseURL: '/api',
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      router.push({ name: 'login' })
    }
    return Promise.reject(error)
  }
)

/**
 * Turns any axios failure into one sentence worth showing a person.
 *
 * Every action in this app used to fail silently: the request rejected, the
 * success toast was skipped, and nothing else happened — so a 403 or a 500
 * looked identical to a click that never registered. This is the single place
 * that decides what a failure reads like, so the wording stays consistent
 * across every page instead of each call site inventing its own.
 *
 * `fallback` is the caller's own specific line ("Could not delete asset."),
 * used when the server sends nothing better. A message the server did send
 * always wins — it is the only thing that can explain WHY, e.g. "Cannot delete
 * a stock item that already has transaction history."
 */
export function errorMessage(error, fallback = '') {
  const t = (key, params) => i18n.global.t(key, params)
  const status = error?.response?.status
  const data = error?.response?.data

  // The request never reached the server (offline, server down, CORS).
  if (!error?.response) {
    return t('errors.network')
  }

  // Laravel validation: surface the first field error, which is the one the
  // user can actually act on. `message` alone is just "The given data was invalid."
  if (status === 422) {
    const first = Object.values(data?.errors || {})[0]
    if (Array.isArray(first) && first[0]) return first[0]
    return data?.message || fallback || t('errors.invalid')
  }

  // A 403 usually carries a message worth reading — "Only the Executive
  // Director can approve disposal requests." explains the rule. But the role
  // middleware's own default is bare jargon and English-only, so swap that one
  // for the translated line and let every specific message through.
  if (status === 403) {
    const generic = ['Unauthorized action.', 'This action is unauthorized.', 'Forbidden']
    return generic.includes(data?.message) ? t('errors.forbidden') : (data?.message || t('errors.forbidden'))
  }
  if (status === 404) return data?.message || t('errors.not_found')
  if (status === 405) return t('errors.not_supported')
  if (status === 419) return t('errors.expired')
  if (status === 429) return t('errors.too_many')
  if (status >= 500) return fallback ? `${fallback} ${t('errors.server_suffix')}` : t('errors.server')

  return data?.message || fallback || t('errors.generic')
}

export default http
