// Builds a CSV Blob from rows using column accessors and triggers a download.
// columns: [{ key, label, value?: (row) => string }]
export function exportCsv(filename, rows, columns) {
  const escape = (v) => {
    const s = v === null || v === undefined ? '' : String(v)
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
  }
  const header = columns.map((c) => escape(c.label)).join(',')
  const lines = rows.map((row) =>
    columns.map((c) => escape(c.value ? c.value(row) : row[c.key])).join(',')
  )
  const csv = [header, ...lines].join('\r\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
