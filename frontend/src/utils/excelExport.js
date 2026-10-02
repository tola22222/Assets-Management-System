import bundledLogoUrl from '../assets/logo/Official PEPY Logo_Green.png'

// Every report export in the app goes through here, so they all share PEPY's
// "Inventory List" template:
//
//   [logo]        អង្គការ លើកកម្ពស់យុវជន          ← organisation, green
//                       Inventory List               ← report title, blue
//              PEPY Office / Learning Center / …     ← scope, bold underlined
//   generated date · filters                         ← small grey note
//   Description ▾ | Qty ▾ | Asset ID ▾ | …           ← red headers, filter & sort dropdowns
//   Motor & Vehicle ( MOV )      | PEY-SR-MOV        ← orange group row (optional)
//   …rows, dotted lines…
//   Total MOV            |  10                       ← group subtotal
//   Total                |  230                      ← grand total
//
// ExcelJS is loaded only when someone exports, so it never weighs on a page.
//
// downloadExcel({
//   fileName, title, subtitle, note,
//   sheets: [{
//     name,
//     columns: [{ key, header, type: 'text'|'qty'|'number'|'money'|'date'|'code', width? }],
//     rows: [{ [key]: value }],
//     group?: { by: (row) => key, label: (key, rows) => string, code?: (key, rows) => string, codeColumn?: key },
//     totalLabel?: string,
//   }],
// })

// The organisation's own name, as on its letterhead (the same in both UI languages).
const ORG_NAME_KM = 'អង្គការ លើកកម្ពស់យុវជន'

const COLORS = {
  org: 'FF0E7A3B',
  title: 'FF1F5FAD',
  header: 'FFC00000',
  note: 'FF7F7F7F',
  line: 'FFBFBFBF',
  group: 'FFFFC000',
  subtotal: 'FFFCE4D6',
  subtotalQty: 'FFF8CBAD',
  total: 'FFE2EFDA',
  totalQty: 'FF00B050',
}

const HEADER_ROW = 5

const SUMMED = ['qty', 'money']

// Excel stores dates without a time zone and ExcelJS writes them as UTC, so a
// calendar date has to be midnight UTC — a local-midnight Date in Cambodia
// (UTC+7) would land on the day before.
function toDate(value) {
  if (!value) return null
  const s = String(value)
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (m) return new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])))
  const d = value instanceof Date ? value : new Date(s)
  if (Number.isNaN(d.getTime())) return null
  // A timestamp: the local calendar day it falls on.
  return new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
}

function cellValue(col, raw) {
  if (raw === null || raw === undefined || raw === '') return null
  if (col.type === 'money' || col.type === 'number' || col.type === 'qty') {
    const n = Number(raw)
    return Number.isFinite(n) ? n : raw
  }
  if (col.type === 'date') return toDate(raw) ?? String(raw)
  return String(raw)
}

function displayLength(value) {
  if (value === null || value === undefined) return 0
  if (value instanceof Date) return 10
  return String(value).length
}

// Reports always carry the green PEPY logo (the letterhead mark) — never the
// logo uploaded in Settings, which is made for the app's sidebar.
async function loadLogo() {
  for (const url of [bundledLogoUrl]) {
    try {
      const res = await fetch(url)
      if (!res.ok) continue
      const blob = await res.blob()
      const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(reader.result)
        reader.onerror = reject
        reader.readAsDataURL(blob)
      })
      const size = await new Promise((resolve) => {
        const img = new Image()
        img.onload = () => resolve({ w: img.naturalWidth, h: img.naturalHeight })
        img.onerror = () => resolve({ w: 3, h: 1 })
        img.src = dataUrl
      })
      const extension = blob.type.includes('jpeg') || blob.type.includes('jpg') ? 'jpeg' : 'png'
      return { dataUrl, extension, ratio: size.w / (size.h || 1) }
    } catch {
      // Try the next source; a report without a logo is still a report.
    }
  }
  return null
}

function styleBorder(cell, { bottom = 'hair' } = {}) {
  cell.border = {
    left: { style: 'thin', color: { argb: COLORS.line } },
    right: { style: 'thin', color: { argb: COLORS.line } },
    bottom: { style: bottom, color: { argb: bottom === 'hair' ? COLORS.line : 'FF808080' } },
  }
}

function alignFor(col) {
  if (col.type === 'money' || col.type === 'number') return { horizontal: 'right', vertical: 'middle' }
  if (col.type === 'qty' || col.type === 'date' || col.type === 'code') return { horizontal: 'center', vertical: 'middle' }
  return { horizontal: 'left', vertical: 'middle' }
}

function numFmtFor(col) {
  if (col.type === 'money') return '"$"#,##0.00'
  if (col.type === 'date') return 'd-mmm-yy'
  return undefined
}

function writeDataRow(ws, columns, row, rowNumber) {
  const r = ws.getRow(rowNumber)
  columns.forEach((col, i) => {
    const cell = r.getCell(i + 1)
    cell.value = cellValue(col, row[col.key])
    cell.alignment = alignFor(col)
    const fmt = numFmtFor(col)
    if (fmt) cell.numFmt = fmt
    cell.font = { name: 'Calibri', size: 10 }
    styleBorder(cell)
  })
}

function fillRow(ws, rowNumber, n, argb, font) {
  const r = ws.getRow(rowNumber)
  for (let c = 1; c <= n; c++) {
    const cell = r.getCell(c)
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb } }
    cell.font = font
    styleBorder(cell, { bottom: 'thin' })
  }
}

function sums(columns, rows) {
  const out = {}
  columns.forEach((col) => {
    if (SUMMED.includes(col.type)) out[col.key] = rows.reduce((s, r) => s + (Number(r[col.key]) || 0), 0)
  })
  return out
}

function writeTotalRow(ws, columns, rowNumber, label, totals, { fill, qtyFill, qtyFont }) {
  const n = columns.length
  fillRow(ws, rowNumber, n, fill, { name: 'Calibri', size: 10, bold: true })
  const r = ws.getRow(rowNumber)
  r.getCell(1).value = label
  r.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' }
  columns.forEach((col, i) => {
    if (!(col.key in totals)) return
    const cell = r.getCell(i + 1)
    cell.value = totals[col.key]
    cell.alignment = alignFor(col)
    const fmt = numFmtFor(col)
    if (fmt) cell.numFmt = fmt
    if (col.type === 'qty') {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: qtyFill } }
      if (qtyFont) cell.font = qtyFont
    }
  })
}

function writeSheet(wb, sheet, { title, subtitle, note, logo }) {
  const ws = wb.addWorksheet((sheet.name || 'Report').replace(/[\\/?*[\]:]/g, ' ').slice(0, 31), {
    pageSetup: { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0, printTitlesRow: `${HEADER_ROW}:${HEADER_ROW}` },
  })
  const { columns } = sheet
  const n = columns.length

  // ---- Title block ----------------------------------------------------------
  const titleRow = (rowNumber, text, font, height) => {
    ws.mergeCells(rowNumber, 1, rowNumber, n)
    const cell = ws.getCell(rowNumber, 1)
    cell.value = text
    cell.font = font
    cell.alignment = { horizontal: 'center', vertical: 'middle' }
    ws.getRow(rowNumber).height = height
  }
  titleRow(1, ORG_NAME_KM, { name: 'Khmer OS Muol Light', size: 14, bold: true, color: { argb: COLORS.org } }, 26)
  titleRow(2, title, { name: 'Cambria', size: 15, color: { argb: COLORS.title } }, 22)
  titleRow(3, subtitle || '', { name: 'Cambria', size: 14, bold: true, underline: true }, 22)
  ws.mergeCells(4, 1, 4, n)
  ws.getCell(4, 1).value = note || ''
  ws.getCell(4, 1).font = { name: 'Calibri', size: 9, italic: true, color: { argb: COLORS.note } }
  ws.getCell(4, 1).alignment = { horizontal: 'right', vertical: 'middle' }

  if (logo) {
    const id = wb.addImage({ base64: logo.dataUrl, extension: logo.extension })
    const height = 64
    ws.addImage(id, { tl: { col: 0.1, row: 0.15 }, ext: { width: Math.round(height * logo.ratio), height } })
  }

  // ---- Header ---------------------------------------------------------------
  const header = ws.getRow(HEADER_ROW)
  header.height = 20
  columns.forEach((col, i) => {
    const cell = header.getCell(i + 1)
    cell.value = col.header
    cell.font = { name: 'Calibri', size: 11, bold: true, color: { argb: COLORS.header } }
    cell.alignment = { horizontal: 'center', vertical: 'middle' }
    cell.border = {
      top: { style: 'thin', color: { argb: 'FF808080' } },
      left: { style: 'thin', color: { argb: COLORS.line } },
      right: { style: 'thin', color: { argb: COLORS.line } },
      bottom: { style: 'thin', color: { argb: 'FF808080' } },
    }
  })

  // ---- Body -----------------------------------------------------------------
  let rowNumber = HEADER_ROW + 1
  if (sheet.group) {
    const groups = new Map()
    sheet.rows.forEach((row) => {
      const key = sheet.group.by(row)
      if (!groups.has(key)) groups.set(key, [])
      groups.get(key).push(row)
    })
    const codeIndex = sheet.group.codeColumn ? columns.findIndex((c) => c.key === sheet.group.codeColumn) : -1
    for (const [key, rows] of groups) {
      // Orange group row: name on the left, its code under the code column.
      fillRow(ws, rowNumber, n, COLORS.group, { name: 'Calibri', size: 10, bold: true })
      ws.getCell(rowNumber, 1).value = sheet.group.label(key, rows)
      if (codeIndex >= 0 && sheet.group.code) {
        const codeCell = ws.getCell(rowNumber, codeIndex + 1)
        codeCell.value = sheet.group.code(key, rows)
        codeCell.alignment = { horizontal: 'center', vertical: 'middle' }
      }
      rowNumber++
      rows.forEach((row) => writeDataRow(ws, columns, row, rowNumber++))
      const code = sheet.group.code ? sheet.group.code(key, rows) : sheet.group.label(key, rows)
      writeTotalRow(ws, columns, rowNumber++, `Total ${code}`, sums(columns, rows), {
        fill: COLORS.subtotal, qtyFill: COLORS.subtotalQty,
      })
    }
  } else {
    sheet.rows.forEach((row) => writeDataRow(ws, columns, row, rowNumber++))
  }

  // Grand total: the row count, plus every quantity / money column summed.
  writeTotalRow(ws, columns, rowNumber, `${sheet.totalLabel || 'Total'}: ${sheet.rows.length}`, sums(columns, sheet.rows), {
    fill: COLORS.total, qtyFill: COLORS.totalQty, qtyFont: { name: 'Calibri', size: 10, bold: true, color: { argb: 'FFFFFFFF' } },
  })

  // ---- Filter & sort dropdowns, frozen header, widths -------------------------
  ws.autoFilter = { from: { row: HEADER_ROW, column: 1 }, to: { row: Math.max(rowNumber - 1, HEADER_ROW), column: n } }
  ws.views = [{ state: 'frozen', ySplit: HEADER_ROW, activeCell: `A${HEADER_ROW + 1}` }]
  columns.forEach((col, i) => {
    const longest = Math.max(displayLength(col.header) + 4, ...sheet.rows.map((r) => displayLength(r[col.key])))
    ws.getColumn(i + 1).width = col.width || Math.min(Math.max(longest + 2, 8), 50)
  })
  // The logo sits over the first column: make sure it is wide enough.
  if (logo && ws.getColumn(1).width < 24) ws.getColumn(1).width = 24
}

/** The workbook itself — no browser APIs, so it can be built and checked anywhere. */
export function buildWorkbook(ExcelJS, { title, subtitle, note, sheets, logo = null }) {
  const wb = new ExcelJS.Workbook()
  wb.creator = 'PEPY Assets'
  wb.created = new Date()
  sheets.forEach((sheet) => writeSheet(wb, sheet, { title, subtitle, note, logo }))
  return wb
}

export async function downloadExcel({ fileName, title, subtitle, note, sheets }) {
  const mod = await import('exceljs')
  const ExcelJS = mod.default ?? mod
  const wb = buildWorkbook(ExcelJS, { title, subtitle, note, sheets, logo: await loadLogo() })

  const buffer = await wb.xlsx.writeBuffer()
  const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${fileName}-${new Date().toISOString().slice(0, 10)}.xlsx`
  link.click()
  URL.revokeObjectURL(url)
}

/** "Generated 2 Oct 2026 14:05" plus any filters, for the note row. */
export function exportNote(generatedLabel, filters = []) {
  const now = new Date()
  const stamp = now.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  return [`${generatedLabel} ${stamp}`, ...filters.filter(Boolean)].join('  ·  ')
}
