@extends('layouts.app')
@section('title', 'Stock Transfers')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    <x-page-header
        title="Stock Transfers"
        subtitle="Transfer stock between locations."
        :buttonText="false"
        :buttonAction="false"
    />

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">Reference</th>
                        <th class="p-4 font-semibold tracking-wide">From</th>
                        <th class="p-4 font-semibold tracking-wide">To</th>
                        <th class="p-4 font-semibold tracking-wide">Date</th>
                        <th class="p-4 font-semibold tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($transfers ?? [] as $transfer)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5 font-mono text-sm font-medium text-gray-900 dark:text-white">{{ $transfer->reference ?? 'N/A' }}</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">{{ $transfer->fromLocation->name ?? $transfer->fromSchool->name ?? 'N/A' }}</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">{{ $transfer->toLocation->name ?? $transfer->toSchool->name ?? 'N/A' }}</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">{{ $transfer->created_at ? $transfer->created_at->format('d M Y') : 'N/A' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 uppercase">{{ $transfer->status ?? 'PENDING' }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No stock transfers found.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
