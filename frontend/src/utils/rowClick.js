// Clicking a table row opens its View — the same thing the row's view icon
// does. A click that lands on a control inside the row (an action button, the
// select checkbox, a link, a field) belongs to that control, and finishing a
// text selection is not a click on the row either.
const CONTROLS = 'button, a, input, select, textarea, label, [role="button"], [role="combobox"]'

export function onRowClick(event, open) {
  if (event.target.closest(CONTROLS)) return
  if (window.getSelection && String(window.getSelection())) return
  open()
}
