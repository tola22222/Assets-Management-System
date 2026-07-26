import { ref, computed } from 'vue'
import http from '../api/http'

// AssetMovement only ever records stock-in and approved transfers — it does not
// cover assignments/returns/disposals. There is no unified backend endpoint, so
// this merges the 5 existing workflow resources' recent rows client-side into one
// real (not fake/seeded) timeline, sorted by date.
const SOURCES = [
  { endpoint: '/asset-movements', type: (r) => (r.movement_type === 'stock_in' ? 'checkin' : 'transfer') },
  { endpoint: '/asset-transfers', type: () => 'transfer' },
  { endpoint: '/asset-assignments', type: () => 'checkout' },
  { endpoint: '/asset-returns', type: () => 'checkin' },
  { endpoint: '/asset-disposals', type: () => 'disposal' },
]

const TYPE_COLOR = { transfer: 'primary', checkout: 'secondary', checkin: 'success', disposal: 'error' }

function normalize(rows, sourceIndex) {
  return rows.map((r) => {
    const type = SOURCES[sourceIndex].type(r)
    return {
      id: `${SOURCES[sourceIndex].endpoint}-${r.id}`,
      type,
      color: TYPE_COLOR[type],
      asset: r.asset,
      from: r.from_location?.name,
      to: r.to_location?.name || (type === 'checkout' ? r.recipient_name : undefined),
      actor: r.requester?.name || r.returned_by?.name,
      status: r.status || (r.verified_at ? 'completed' : undefined),
      date: r.created_at,
    }
  })
}

export function useStockMotion() {
  const items = ref([])
  const loading = ref(false)
  const raw = ref({})

  async function load() {
    loading.value = true
    try {
      const results = await Promise.all(
        SOURCES.map(({ endpoint }) => http.get(endpoint, { params: { per_page: 50, sort_by: 'created_at', sort_dir: 'desc' } }))
      )
      const merged = []
      results.forEach(({ data }, i) => {
        const rows = data.data ?? data
        raw.value[SOURCES[i].endpoint] = rows
        merged.push(...normalize(rows, i))
      })
      merged.sort((a, b) => new Date(b.date) - new Date(a.date))
      items.value = merged
    } finally {
      loading.value = false
    }
  }

  const stats = computed(() => {
    const now = new Date()
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
    const movementsThisMonth = items.value.filter((i) => new Date(i.date) >= startOfMonth).length
    const pendingTransfers = items.value.filter((i) => i.type === 'transfer' && i.status === 'pending').length
    const checkedOut = items.value.filter((i) => i.type === 'checkout' && (i.status === 'assigned' || i.status === 'active')).length
    const overdueReturns = items.value.filter((i) => i.type === 'checkout' && i.status === 'overdue').length
    return { movementsThisMonth, pendingTransfers, checkedOut, overdueReturns }
  })

  return { items, loading, stats, load }
}
