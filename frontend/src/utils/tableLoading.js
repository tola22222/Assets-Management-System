// Loading state for every data table, in one place.
//
// While a page is fetching its data (any GET through the shared axios
// instance), <html> carries data-fetching, and main.css shows a spinner over
// each .data-table and dims its rows; when the last request settles the data
// is shown. Nothing in the page templates changes, so every list — and any
// table added later — gets the same loading state without wiring one up.
//
// Background polling (the notification bell, the dashboard's silent refresh)
// is left out, or tables would flicker every few seconds.
const BACKGROUND = [/^\/?notifications(\/|$|\?)/, /^\/?dashboard(\/|$|\?)/, /^\/?branding(\/|$|\?)/]
// Local requests can finish in a few milliseconds: keep the spinner up long
// enough to be seen rather than flash.
const MIN_VISIBLE_MS = 350

let pending = 0
let shownAt = 0
let hideTimer = null

function show() {
  clearTimeout(hideTimer)
  if (!shownAt) shownAt = Date.now()
  document.documentElement.setAttribute('data-fetching', '')
}

function hide() {
  const wait = Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt))
  clearTimeout(hideTimer)
  hideTimer = setTimeout(() => {
    if (pending > 0) return
    shownAt = 0
    document.documentElement.removeAttribute('data-fetching')
  }, wait)
}

const counts = (config) => (config?.method || 'get').toLowerCase() === 'get'
  && !BACKGROUND.some((re) => re.test(String(config?.url || '')))

export function installTableLoading(http) {
  http.interceptors.request.use((config) => {
    if (counts(config)) {
      config.tableLoading = true
      pending++
      show()
    }
    return config
  }, null, { synchronous: true })

  const finish = (config) => {
    if (!config?.tableLoading) return
    config.tableLoading = false
    pending = Math.max(0, pending - 1)
    if (pending === 0) hide()
  }
  http.interceptors.response.use(
    (response) => {
      finish(response.config)
      return response
    },
    (error) => {
      finish(error.config)
      return Promise.reject(error)
    },
  )
}
