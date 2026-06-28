@extends('layouts.app')
@section('title', $asset->name)
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <a href="{{ route('assets.index') }}" class="inline-flex items-center gap-2 text-sm text-brand font-semibold hover:underline">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        Back to Assets
    </a>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ $asset->name }}</h1>
                    <p class="text-sm font-mono text-gray-400 dark:text-gray-500 mt-1">{{ $asset->asset_code }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($asset->qr_code_url)
                        <a href="{{ route('assets.download-qr', $asset->id) }}"
                            class="border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold text-sm px-4 py-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition">Download QR</a>
                        <a href="{{ route('assets.print-qr', $asset->id) }}" target="_blank"
                            class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-4 py-2 rounded-xl shadow-sm transition">Print QR</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-4">
                @if($asset->image_path)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <img src="{{ asset('storage/'.$asset->image_path) }}" class="w-full">
                    </div>
                @else
                    <div class="bg-gray-100 dark:bg-gray-900 rounded-xl h-48 flex items-center justify-center text-gray-400 dark:text-gray-500 border border-gray-200 dark:border-gray-700">No Image</div>
                @endif
                @if($asset->qr_code_url)
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                        <img src="{{ $asset->qr_code_url }}" alt="QR" class="w-20 h-20 mx-auto">
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Scan to view asset details</p>
                    </div>
                @endif
            </div>

            <div class="md:col-span-2 grid grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Category</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">{{ $asset->category->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Brand</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">{{ $asset->brand ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Model</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">{{ $asset->model ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Serial Number</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">{{ $asset->serial_number ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Condition</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">{{ ucfirst($asset->condition ?? 'N/A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Status</p>
                    <span class="inline-block mt-0.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $asset->status === 'active' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800' }}">{{ strtoupper($asset->status) }}</span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Purchase Date</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">{{ $asset->purchase_date ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Purchase Price</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">${{ number_format($asset->purchase_price ?? 0, 2) }}</p>
                </div>
                @if($asset->description)
                <div class="col-span-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Description</p>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">{{ $asset->description }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    @if($asset->assignments->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Assignment History</h3>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="p-4 pl-5">Assigned To</th>
                    <th class="p-4">Location</th>
                    <th class="p-4">Date</th>
                    <th class="p-4 pr-5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($asset->assignments as $a)
                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                    <td class="p-4 pl-5 font-medium text-gray-800 dark:text-gray-200">{{ $a->recipient_name }}</td>
                    <td class="p-4 text-gray-500 dark:text-gray-400">{{ $a->location->name ?? 'N/A' }}</td>
                    <td class="p-4 text-gray-500 dark:text-gray-400">{{ $a->assigned_date ? $a->assigned_date->format('d M Y') : 'N/A' }}</td>
                    <td class="p-4 pr-5"><span class="px-2 py-1 text-xs font-semibold rounded-full {{ $a->status === 'active' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800' }}">{{ strtoupper($a->status) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($asset->verifications->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Verification History</h3>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="p-4 pl-5">Date</th>
                    <th class="p-4">Condition</th>
                    <th class="p-4 pr-5">Remark</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($asset->verifications as $v)
                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                    <td class="p-4 pl-5 text-gray-500 dark:text-gray-400">{{ $v->verified_at ? $v->verified_at->format('d M Y') : 'N/A' }}</td>
                    <td class="p-4"><span class="px-2 py-1 text-xs font-semibold rounded-full {{ $v->condition === 'good' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : ($v->condition === 'fair' ? 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30' : 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30') }}">{{ ucfirst($v->condition) }}</span></td>
                    <td class="p-4 pr-5 text-gray-500 dark:text-gray-400">{{ $v->remark ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
