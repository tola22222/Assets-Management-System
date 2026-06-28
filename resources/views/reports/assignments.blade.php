@extends('layouts.app')
@section('title', 'Assignment Report')
@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Assignment Report</h1>
            <p class="text-slate-500 dark:text-gray-400">Total: {{ $assignments->count() }} assignments</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('reports.assignments', ['export' => 'csv']) }}" class="px-4 py-2 border border-slate-200 dark:border-gray-700 rounded-xl text-sm font-bold text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-800">Export CSV</a>
            <button onclick="window.print()" class="px-4 py-2 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark">Print</button>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Asset</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Assigned To</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Type</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Date</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
                @forelse($assignments as $a)
                <tr>
                    <td class="px-6 py-4 font-bold text-slate-700 dark:text-gray-300">{{ $a->asset->name ?? 'N/A' }}</td>
                    <td class="px-6 py-4 dark:text-gray-300">{{ $a->recipient_name }}</td>
                    <td class="px-6 py-4 capitalize text-sm dark:text-gray-400">{{ $a->assigned_to_type }}</td>
                    <td class="px-6 py-4 text-sm dark:text-gray-400">{{ $a->assigned_date ? $a->assigned_date->format('d M Y') : 'N/A' }}</td>
                    <td class="px-6 py-4 dark:text-gray-300">{{ strtoupper($a->status) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400 dark:text-gray-500">No assignments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
