@extends('layouts.app')
@section('title', 'Asset: ' . $asset->asset_code)
@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    <a href="{{ route('qr-scan.index') }}" class="text-sm text-brand font-bold hover:underline">&larr; Scan Another</a>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <div class="p-8 border-b border-slate-100 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-white">{{ $asset->name }}</h1>
                    <p class="text-sm font-mono text-slate-400 dark:text-gray-500 mt-1">{{ $asset->asset_code }}</p>
                </div>
                @if($asset->qr_code_url)
                    <img src="{{ $asset->qr_code_url }}" alt="QR" class="w-20 h-20">
                @endif
            </div>
        </div>

        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                @if($asset->image_path)
                    <img src="{{ asset('storage/'.$asset->image_path) }}" class="w-full rounded-xl border border-slate-200 dark:border-gray-700">
                @else
                    <div class="bg-slate-100 dark:bg-gray-700 rounded-xl h-48 flex items-center justify-center text-slate-400 dark:text-gray-500">No Image</div>
                @endif
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Category</p>
                        <p class="font-bold text-slate-700 dark:text-gray-300">{{ $asset->category->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Brand</p>
                        <p class="font-bold text-slate-700 dark:text-gray-300">{{ $asset->brand ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Model</p>
                        <p class="font-bold text-slate-700 dark:text-gray-300">{{ $asset->model ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Serial Number</p>
                        <p class="font-bold text-slate-700 dark:text-gray-300">{{ $asset->serial_number ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Condition</p>
                        <p class="font-bold text-slate-700 dark:text-gray-300">{{ ucfirst($asset->condition ?? 'N/A') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Status</p>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $asset->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-400' }}">
                            {{ strtoupper($asset->status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Purchase Date</p>
                        <p class="font-bold text-slate-700 dark:text-gray-300">{{ $asset->purchase_date ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Warranty</p>
                        <p class="font-bold text-slate-700 dark:text-gray-300">{{ $asset->purchase_date ? date('Y-m-d', strtotime($asset->purchase_date . ' +1 year')) : 'N/A' }}</p>
                    </div>
                </div>

                @if($asset->location)
                <div class="pt-4 border-t border-slate-100 dark:border-gray-700">
                    <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold mb-2">Current Location</p>
                    <p class="font-bold text-slate-700 dark:text-gray-300">{{ $asset->location->name }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Verification Form --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-gray-700">
            <h3 class="font-bold text-slate-800 dark:text-white">Verify This Asset</h3>
            <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Record an audit/verification for this asset.</p>
        </div>
        <form action="{{ route('qr-scan.verify', $asset->asset_code) }}" method="POST" class="p-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 dark:text-gray-300">Location <span class="text-red-500">*</span></label>
                    <select name="location_id" required
                        class="w-full bg-white dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                        <option value="">Select location</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 dark:text-gray-300">Condition <span class="text-red-500">*</span></label>
                    <select name="condition" required
                        class="w-full bg-white dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                        <option value="">Select condition</option>
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="broken">Broken</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 dark:text-gray-300">Remark</label>
                    <input type="text" name="remark" placeholder="Optional notes..."
                        class="w-full bg-white dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Submit Verification
                </button>
            </div>
        </form>
    </div>

    @if($asset->assignments->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-gray-700">
            <h3 class="font-bold text-slate-800 dark:text-white">Assignment History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Assigned To</th>
                        <th class="px-6 py-3 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Date</th>
                        <th class="px-6 py-3 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-gray-800">
                    @foreach($asset->assignments as $a)
                    <tr>
                        <td class="px-6 py-3 text-sm font-bold text-slate-700 dark:text-gray-300">{{ $a->recipient_name }}</td>
                        <td class="px-6 py-3 text-sm text-slate-500 dark:text-gray-400">{{ $a->assigned_date ? $a->assigned_date->format('d M Y') : 'N/A' }}</td>
                        <td class="px-6 py-3"><span class="px-2 py-1 text-xs font-bold rounded {{ $a->status_badge_class }}">{{ strtoupper($a->status) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($asset->verifications->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-gray-700">
            <h3 class="font-bold text-slate-800 dark:text-white">Verification History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Date</th>
                        <th class="px-6 py-3 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Condition</th>
                        <th class="px-6 py-3 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">Remark</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-gray-800">
                    @foreach($asset->verifications as $v)
                    <tr>
                        <td class="px-6 py-3 text-sm text-slate-500 dark:text-gray-400">{{ $v->verified_at ? $v->verified_at->format('d M Y') : 'N/A' }}</td>
                        <td class="px-6 py-3"><span class="px-2 py-1 text-xs font-bold rounded {{ $v->condition === 'good' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : ($v->condition === 'fair' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400') }}">{{ ucfirst($v->condition) }}</span></td>
                        <td class="px-6 py-3 text-sm text-slate-500 dark:text-gray-400">{{ $v->remark ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
