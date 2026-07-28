@extends('layouts.app')
@section('title', 'Inventory Report')
@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Inventory Report</h1>
            <p class="text-slate-500 dark:text-gray-400">Total: {{ $assets->count() }} assets</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('reports.inventory', ['export' => 'csv']) }}" class="px-4 py-2 border border-slate-200 dark:border-gray-700 rounded-xl text-sm font-bold text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-800">Export CSV</a>
            <button onclick="window.print()" class="px-4 py-2 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark">Print</button>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Code</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Name</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Category</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Condition</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Price</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
                @forelse($assets as $asset)
                <tr>
                    <td class="px-6 py-4 font-mono text-sm text-slate-500 dark:text-gray-400">{{ $asset->asset_code }}</td>
                    <td class="px-6 py-4 font-bold text-slate-700 dark:text-gray-300">{{ $asset->name }}</td>
                    <td class="px-6 py-4 text-sm dark:text-gray-300">{{ $asset->category->name ?? 'N/A' }}</td>
                    <td class="px-6 py-4 capitalize text-sm dark:text-gray-300">{{ $asset->condition ?? 'N/A' }}</td>
                    <td class="px-6 py-4 dark:text-gray-300">{{ strtoupper($asset->status) }}</td>
                    <td class="px-6 py-4 font-bold dark:text-gray-200">${{ number_format($asset->purchase_price ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400 dark:text-gray-500">No assets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
