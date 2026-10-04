// Loading state for every action button, app-wide.
//
// When a button click (or a form submit) sends a save request — POST, PUT,
// PATCH or DELETE through the shared axios instance — that button is disabled
// and shows a spinner in place of its icon until the request finishes or
// fails, then goes back to exactly how it was. So Create, Save, Update, Delete,
// Submit, Transfer, Confirm, Approve… can't be double-clicked, and every page
// gets this without its own `saving` flag.
//
// It tracks the button the person last pressed: a request that starts within
// a short window of that press belongs to it. Reads (GET) never count, so
// opening a dialog, searching or filtering is unaffected. A button can opt out
// with `data-no-loading`.
//
// Confirm dialogs (ConfirmDialog, `.dialog-card`) fade away as soon as their
// button is pressed, so the spinner would be invisible there. The button that
// OPENED the dialog — a row's Delete icon, "Delete selected", "Restore" —
// shows the loading state as well, and it stays on screen until the work is done.

import i18n from '../i18n'

const MUTATING = new Set(['post', 'put', 'patch', 'delete'])
// How long after a press a request still counts as that press's action (some
// actions validate or read something first). Refreshed while it keeps firing.
const WINDOW_MS = 3000
// Local saves can finish in ~200 ms — too quick to see. The spinner stays at
// least this long; only the button's return is held, never the action itself.
const MIN_VISIBLE_MS = 400

let trigger = null // { button, opener, at }

// What the label says while the request runs, read off the request itself:
// "Saving…", "Deleting…", "Approving…" … (loading.* in en.json / km.json).
function loadingLabel(config) {
  const method = (config.method || 'post').toLowerCase()
  const url = String(config.url || '')
  const key = method === 'delete' ? 'deleting'
    : /\/approve$/.test(url) ? 'approving'
    : /\/(reject|decline)$/.test(url) ? 'rejecting'
    : /\/confirm$/.test(url) ? 'confirming'
    : /\/return$/.test(url) ? 'returning'
    : /\/restore$/.test(url) ? 'restoring'
    : /(\/email|test-mail)$/.test(url) ? 'sending'
    : /\/import$/.test(url) ? 'importing'
    : /^\/?login$/.test(url) ? 'signing_in'
    : 'saving'
  return i18n.global.t(`loading.${key}`)
}

// A button with words in it (not just an icon) shows the label while loading.
function hasText(button) {
  return [...button.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim())
}

function remember(button) {
  if (!button || button.disabled || button.hasAttribute('data-no-loading')) return
  const inConfirmDialog = !!button.closest('.dialog-card')
  const opener = inConfirmDialog && trigger && trigger.button !== button ? (trigger.opener || trigger.button) : null
  trigger = { button, opener, at: Date.now() }
}

function start(button, label) {
  const count = Number(button.dataset.loadingCount || 0)
  if (count === 0) {
    if (button.dataset.loadingWasDisabled === undefined) button.dataset.loadingWasDisabled = button.disabled ? '1' : '0'
    button.dataset.loadingSince = String(Date.now())
    if (label && hasText(button)) {
      // Same width as before, so a shorter "Saving…" doesn't make it jump.
      button.style.minWidth = `${button.offsetWidth}px`
      button.style.setProperty('--loading-font-size', getComputedStyle(button).fontSize)
      button.dataset.loadingLabel = label
      button.classList.add('has-loading-label')
    }
    button.disabled = true
    button.classList.add('is-loading')
    button.setAttribute('aria-busy', 'true')
  }
  button.dataset.loadingCount = String(count + 1)
}

function stop(button) {
  const count = Number(button.dataset.loadingCount || 0) - 1
  if (count > 0) {
    button.dataset.loadingCount = String(count)
    return
  }
  delete button.dataset.loadingCount
  const shown = Date.now() - Number(button.dataset.loadingSince || 0)
  setTimeout(() => {
    // Pressed again in the meantime: that new action owns the button now.
    if (button.dataset.loadingCount) return
    button.classList.remove('is-loading', 'has-loading-label')
    button.removeAttribute('aria-busy')
    delete button.dataset.loadingLabel
    button.style.removeProperty('min-width')
    button.style.removeProperty('--loading-font-size')
    // Back to how it was: only re-enable a button that was enabled before.
    if (button.dataset.loadingWasDisabled === '0') button.disabled = false
    delete button.dataset.loadingWasDisabled
    delete button.dataset.loadingSince
  }, Math.max(0, MIN_VISIBLE_MS - shown))
}

export function installActionLoading(http) {
  if (typeof document === 'undefined') return

  // Capture phase, so the press is known before the page's own handler runs.
  document.addEventListener('click', (event) => {
    remember(event.target instanceof Element ? event.target.closest('button') : null)
  }, true)
  // Enter in a form submits it without a click: use the submit button.
  document.addEventListener('submit', (event) => {
    remember(event.submitter || event.target.querySelector('button[type="submit"], button:not([type])'))
  }, true)

  http.interceptors.request.use((config) => {
    if (!MUTATING.has((config.method || 'get').toLowerCase())) return config
    const t = trigger
    if (!t || Date.now() - t.at > WINDOW_MS) return config
    const buttons = [t.button, t.opener].filter((b) => b && b.isConnected)
    if (!buttons.length) return config
    t.at = Date.now() // several saves from one action (e.g. bulk delete) all count
    config.actionButtons = buttons
    const label = loadingLabel(config)
    buttons.forEach((b) => start(b, label))
    return config
  }, null, { synchronous: true }) // on the same click, not a tick later

  const finish = (config) => {
    config?.actionButtons?.forEach(stop)
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
