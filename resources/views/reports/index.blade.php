@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="max-w-screen-xl mx-auto px-4 py-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Reports & Analytics</h1>
            <p class="text-sm text-slate-500 mt-0.5">PEPY Asset Management — full system overview</p>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-dark transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
            </svg>
            Export
        </a>
    </div>

    {{-- Tab Nav --}}
    <div class="flex gap-1 bg-slate-100 p-1 rounded-xl w-fit flex-wrap">
        @php
            $tabs = [
                'procurement' => ['label' => 'Procurement',       'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5h12M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z'],
                'inventory'   => ['label' => 'Inventory Control', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                'operations'  => ['label' => 'Operations',        'icon' => 'M17 20H7m10 0v-2a5 5 0 00-10 0v2m5-13a3 3 0 110-6 3 3 0 010 6z'],
                'qa'          => ['label' => 'Quality Assurance', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ];
        @endphp
        @foreach($tabs as $key => $tab)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all
                      {{ $activeTab === $key ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/>
                </svg>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 1 — PROCUREMENT
    ══════════════════════════════════════════════════════ --}}
    @if($activeTab === 'procurement')

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @php
                $pCards = [
                    ['label' => 'Pending Orders',       'value' => $procurementStats['pending_count'],      'color' => 'text-amber-600',  'bg' => 'bg-amber-50'],
                    ['label' => 'Received This Month',  'value' => $procurementStats['completed_month'],    'color' => 'text-brand',      'bg' => 'bg-brand-50'],
                    ['label' => 'Total Spend (USD)',     'value' => '$'.number_format($procurementStats['total_procurement'], 2), 'color' => 'text-slate-800', 'bg' => 'bg-slate-50'],
                ];
            @endphp
            @foreach($pCards as $card)
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold {{ $card['color'] }} mt-1">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap gap-3 items-end bg-white border border-slate-200 rounded-2xl p-4">
            <input type="hidden" name="tab" value="procurement">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Quick View</label>
                <select name="view" class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand outline-none">
                    <option value="">All Time</option>
                    <option value="day"   {{ request('view') == 'day'   ? 'selected' : '' }}>Today</option>
                    <option value="month" {{ request('view') == 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="year"  {{ request('view') == 'year'  ? 'selected' : '' }}>This Year</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}"
                    class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}"
                    class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand outline-none">
            </div>
            <button type="submit" class="px-4 py-2 bg-brand text-white text-sm font-medium rounded-lg hover:bg-brand-dark transition">
                Filter
            </button>
            <a href="{{ route('reports.index', ['tab' => 'procurement']) }}" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-800 border border-slate-200 rounded-lg">
                Reset
            </a>
        </form>

        {{-- Table --}}
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800 text-sm">Purchase Orders</h2>
                <p class="text-xs text-slate-400 mt-0.5">Suppliers · purchase_orders · purchase_order_items</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left">PO Number</th>
                            <th class="px-5 py-3 text-left">Supplier</th>
                            <th class="px-5 py-3 text-left">Items</th>
                            <th class="px-5 py-3 text-left">Total (USD)</th>
                            <th class="px-5 py-3 text-left">Status</th>
                            <th class="px-5 py-3 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($procurementOrders as $order)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $order->po_number ?? 'PO-'.$order->id }}</td>
                                <td class="px-5 py-3 text-slate-800">{{ $order->supplier->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $order->items->count() }} item(s)</td>
                                <td class="px-5 py-3 font-semibold text-slate-800">${{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $badge = match($order->status) {
                                            'received' => 'bg-brand-50 text-brand',
                                            'pending'  => 'bg-amber-50 text-amber-700',
                                            'cancelled'=> 'bg-red-50 text-red-600',
                                            default    => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ $order->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-400 text-sm">No purchase orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($procurementOrders->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $procurementOrders->links() }}
                </div>
            @endif
        </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 2 — INVENTORY CONTROL
    ══════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'inventory')

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @php
                $iCards = [
                    ['label' => 'Total Assets',      'value' => $inventoryStats['total_assets'],     'color' => 'text-brand'],
                    ['label' => 'Total Stock Units',  'value' => $inventoryStats['total_stock'],      'color' => 'text-slate-800'],
                    ['label' => 'Categories',         'value' => $inventoryStats['total_categories'], 'color' => 'text-violet-600'],
                    ['label' => 'Low Stock Alerts',   'value' => $inventoryStats['low_stock'],        'color' => 'text-red-500'],
                ];
            @endphp
            @foreach($iCards as $card)
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold {{ $card['color'] }} mt-1">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Table --}}
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800 text-sm">Asset Stock by Location</h2>
                <p class="text-xs text-slate-400 mt-0.5">asset_stock · asset_movements · locations · asset_categories</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left">Asset Code</th>
                            <th class="px-5 py-3 text-left">Name / Brand</th>
                            <th class="px-5 py-3 text-left">Category</th>
                            <th class="px-5 py-3 text-left">Location & Qty</th>
                            <th class="px-5 py-3 text-center">Total Units</th>
                            <th class="px-5 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($assets as $asset)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $asset->asset_code }}</td>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-800">{{ $asset->name }}</div>
                                    <div class="text-xs text-slate-400">{{ $asset->brand ?? 'No Brand' }} · {{ $asset->model ?? 'N/A' }}</div>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $asset->category->name ?? 'Uncategorized' }}</td>
                                <td class="px-5 py-3">
                                    @forelse($asset->stocks as $stock)
                                        <div class="text-xs text-slate-600">
                                            {{ $stock->location->name ?? 'Unknown' }}:
                                            <span class="font-semibold text-slate-800">{{ $stock->quantity }}</span>
                                        </div>
                                    @empty
                                        <span class="text-xs text-red-400">No location assigned</span>
                                    @endforelse
                                </td>
                                <td class="px-5 py-3 text-center font-bold text-slate-800">
                                    {{ $asset->stocks->sum('quantity') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                        {{ $asset->status === 'active' ? 'bg-brand-50 text-brand' : 'bg-slate-100 text-slate-500' }}">
                                        {{ ucfirst($asset->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-400 text-sm">No assets found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($assets->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $assets->links() }}
                </div>
            @endif
        </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 3 — OPERATIONS (CUSTODY)
    ══════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'operations')

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @php
                $oCards = [
                    ['label' => 'Active Assignments',    'value' => $operationsStats['active_assignments'],  'color' => 'text-brand'],
                    ['label' => 'Returned This Month',   'value' => $operationsStats['returned_this_month'], 'color' => 'text-slate-800'],
                    ['label' => 'Overdue',               'value' => $operationsStats['overdue'],             'color' => 'text-red-500'],
                ];
            @endphp
            @foreach($oCards as $card)
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold {{ $card['color'] }} mt-1">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap gap-3 items-end bg-white border border-slate-200 rounded-2xl p-4">
            <input type="hidden" name="tab" value="operations">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select name="assignment_status" class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand outline-none">
                    <option value="">All</option>
                    <option value="active"   {{ request('assignment_status') == 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="returned" {{ request('assignment_status') == 'returned' ? 'selected' : '' }}>Returned</option>
                    <option value="overdue"  {{ request('assignment_status') == 'overdue'  ? 'selected' : '' }}>Overdue</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-brand text-white text-sm font-medium rounded-lg hover:bg-brand-dark transition">Filter</button>
            <a href="{{ route('reports.index', ['tab' => 'operations']) }}" class="px-4 py-2 text-sm text-slate-500 border border-slate-200 rounded-lg hover:text-slate-800">Reset</a>
        </form>

        {{-- Table --}}
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800 text-sm">Asset Assignments (Custody)</h2>
                <p class="text-xs text-slate-400 mt-0.5">asset_assignments · staff · programs</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left">Asset</th>
                            <th class="px-5 py-3 text-left">Assigned To</th>
                            <th class="px-5 py-3 text-left">Type</th>
                            <th class="px-5 py-3 text-left">Assigned Date</th>
                            <th class="px-5 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($assignments as $assignment)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-800">{{ $assignment->asset->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $assignment->asset->asset_code ?? '' }}</div>
                                </td>
                                <td class="px-5 py-3 text-slate-700">
                                    {{ $assignment->assignee_name ?? $assignment->recipient_name ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                        {{ $assignment->assigned_to_type === 'staff' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                        {{ ucfirst($assignment->assigned_to_type ?? 'N/A') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ optional($assignment->assigned_date)->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $badge = match($assignment->status) {
                                            'active'   => 'bg-brand-50 text-brand',
                                            'returned' => 'bg-slate-100 text-slate-500',
                                            'overdue'  => 'bg-red-50 text-red-500',
                                            default    => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ ucfirst($assignment->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-slate-400 text-sm">No assignments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($assignments->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $assignments->links() }}
                </div>
            @endif
        </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 4 — QUALITY ASSURANCE
    ══════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'qa')

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @php
                $qCards = [
                    ['label' => 'Total Verifications', 'value' => $qaStats['total_verifications'], 'color' => 'text-slate-800'],
                    ['label' => 'This Month',           'value' => $qaStats['this_month'],          'color' => 'text-brand'],
                    ['label' => 'Issues Found',         'value' => $qaStats['issues_found'],        'color' => 'text-red-500'],
                    ['label' => 'Good Condition',       'value' => $qaStats['good_condition'],      'color' => 'text-brand'],
                ];
            @endphp
            @foreach($qCards as $card)
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold {{ $card['color'] }} mt-1">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap gap-3 items-end bg-white border border-slate-200 rounded-2xl p-4">
            <input type="hidden" name="tab" value="qa">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Condition Found</label>
                <select name="verification_status" class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand outline-none">
                    <option value="">All</option>
                    <option value="good"    {{ request('verification_status') == 'good'    ? 'selected' : '' }}>Good</option>
                    <option value="damaged" {{ request('verification_status') == 'damaged' ? 'selected' : '' }}>Damaged</option>
                    <option value="missing" {{ request('verification_status') == 'missing' ? 'selected' : '' }}>Missing</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-brand text-white text-sm font-medium rounded-lg hover:bg-brand-dark transition">Filter</button>
            <a href="{{ route('reports.index', ['tab' => 'qa']) }}" class="px-4 py-2 text-sm text-slate-500 border border-slate-200 rounded-lg hover:text-slate-800">Reset</a>
        </form>

        {{-- Table --}}
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800 text-sm">Asset Verifications</h2>
                <p class="text-xs text-slate-400 mt-0.5">asset_verifications</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left">Asset</th>
                            <th class="px-5 py-3 text-left">Location</th>
                            <th class="px-5 py-3 text-left">Verified By</th>
                            <th class="px-5 py-3 text-left">Quantity</th>
                            <th class="px-5 py-3 text-left">Condition</th>
                            <th class="px-5 py-3 text-left">Remark</th>
                            <th class="px-5 py-3 text-left">Verified At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($verifications as $verification)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-800">{{ $verification->asset->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $verification->asset->asset_code ?? '' }}</div>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $verification->location->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-700">
                                    @php
                                        $user = \App\Models\User::find($verification->verified_by);
                                    @endphp
                                    {{ $user ? $user->name : ($verification->verified_by ? 'User #'.$verification->verified_by : '—') }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $verification->quantity_verified ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $badge = match($verification->condition) {
                                            'good'    => 'bg-brand-50 text-brand',
                                            'damaged' => 'bg-amber-50 text-amber-700',
                                            'missing' => 'bg-red-50 text-red-500',
                                            default   => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ ucfirst($verification->condition ?? 'N/A') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-500 text-xs max-w-xs truncate">{{ $verification->remark ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ optional($verification->verified_at)->format('d M Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-slate-400 text-sm">No verifications found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($verifications->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $verifications->links() }}
                </div>
            @endif
        </div>

    @endif

</div>
@endsection