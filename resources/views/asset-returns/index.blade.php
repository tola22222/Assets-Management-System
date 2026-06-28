@extends('layouts.app')
@section('title', 'Asset Returns')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    <x-page-header title="Asset Returns" subtitle="Manage asset return requests"
        buttonText="{{ Auth::user()->isStaff() ? 'Request Return' : 'Record Return' }}" buttonAction="openModal()" />

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">Asset</th>
                        <th class="p-4 font-semibold tracking-wide">Returned By</th>
                        <th class="p-4 font-semibold tracking-wide">Condition</th>
                        <th class="p-4 font-semibold tracking-wide">Date</th>
                        <th class="p-4 font-semibold tracking-wide">Status</th>
                        <th class="p-4 pr-5 font-semibold tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($returns as $return)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5 font-medium text-gray-900 dark:text-white">{{ $return->asset->name ?? 'N/A' }}</td>
                        <td class="p-4">{{ $return->returnedBy->name ?? 'N/A' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                {{ $return->condition === 'good' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' : ($return->condition === 'fair' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300') }}">
                                {{ ucfirst($return->condition) }}
                            </span>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">{{ $return->return_date ? $return->return_date->format('d M Y') : 'N/A' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                {{ $return->status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' : ($return->status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300') }}">
                                {{ strtoupper($return->status) }}
                            </span>
                        </td>
                        <td class="p-4 pr-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('asset-returns.show', $return) }}"
                                    class="w-7 h-7 bg-amber-500 text-white rounded flex items-center justify-center hover:bg-amber-600 transition shadow-sm" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </a>
                                @if(Auth::user()->isAdmin() && $return->status === 'pending')
                                <form action="{{ route('asset-returns.approve', $return) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="w-7 h-7 bg-brand text-white rounded flex items-center justify-center hover:bg-brand-dark transition shadow-sm" title="Approve">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    </button>
                                </form>
                                <form action="{{ route('asset-returns.reject', $return) }}" method="POST" class="inline">
                                    @csrf @method('PUT')
                                    <button type="submit"
                                        class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Reject">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No return records found.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="returnModal" class="fixed inset-0 bg-gray-900/40 dark:bg-gray-950/60 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 id="modalTitle" class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">{{ Auth::user()->isStaff() ? 'Request Return' : 'Record Return' }}</h3>
            <button onclick="closeModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('asset-returns.store') }}" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto">
            @csrf
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Assignment <span class="text-red-500">*</span></label>
                    <select name="assignment_id" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                        <option value="">Select Assignment</option>
                        @foreach($assignments as $a)
                            <option value="{{ $a->id }}">{{ $a->asset->name ?? 'N/A' }} - {{ $a->recipient_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Asset ID <span class="text-red-500">*</span></label>
                    <select name="asset_id" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                        <option value="">Select Asset</option>
                        @foreach($assignments as $a)
                            <option value="{{ $a->asset_id }}">{{ $a->asset->name ?? 'N/A' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Condition <span class="text-red-500">*</span></label>
                    <select name="condition" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="damaged">Damaged</option>
                        <option value="broken">Broken</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Damage Notes</label>
                    <textarea name="damage_notes" rows="2"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:border-brand transition"></textarea>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Photo Evidence</label>
                    <input type="file" name="image" accept="image/*"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:border-brand transition file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand hover:file:bg-brand-100">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Return Date <span class="text-red-500">*</span></label>
                    <input type="date" name="return_date" required value="{{ date('Y-m-d') }}"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:border-brand transition">
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="closeModal()"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <button type="submit"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">Submit Return</button>
            </div>
        </form>
    </div>
</div>

<style>
.animate__slide-in-right { animation: slideInRight 0.2s ease-out; }
@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<script>
    function openModal() {
        document.getElementById('returnModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('returnModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
</script>
@endsection
