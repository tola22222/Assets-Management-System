@extends('layouts.app')
@section('title', 'Asset Verifications')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    @if(Auth::user()->isOperationsHrManager())
    <x-page-header title="Asset Verifications" subtitle="Verify asset conditions"
        buttonText="New Verification" buttonAction="openVerificationModal()" />
    @else
    <x-page-header title="My Verifications" subtitle="Your verification records." />
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">Asset</th>
                        <th class="p-4 font-semibold tracking-wide">Location</th>
                        <th class="p-4 font-semibold tracking-wide">Verified By</th>
                        <th class="p-4 font-semibold tracking-wide">Condition</th>
                        <th class="p-4 font-semibold tracking-wide">Date</th>
                        <th class="p-4 font-semibold tracking-wide">Status</th>
                        @if(Auth::user()->isOperationsHrManager())
                        <th class="p-4 pr-5 font-semibold tracking-wide text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($verifications as $verification)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5 font-medium text-gray-900 dark:text-white">{{ $verification->asset->name ?? 'N/A' }}</td>
                        <td class="p-4">{{ $verification->location->name ?? 'N/A' }}</td>
                        <td class="p-4">{{ $verification->verifiedBy->name ?? 'N/A' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                {{ $verification->condition === 'good' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' : ($verification->condition === 'fair' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : ($verification->condition === 'broken' ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400')) }}">
                                {{ ucfirst($verification->condition) }}
                            </span>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">{{ $verification->verified_at ? $verification->verified_at->format('d M Y') : 'Pending' }}</td>
                        <td class="p-4">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg {{ $verification->verified_at ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' }}">
                                {{ $verification->verified_at ? 'COMPLETED' : 'PENDING' }}
                            </span>
                        </td>
                        @if(Auth::user()->isOperationsHrManager())
                        <td class="p-4 pr-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if(!$verification->verified_at)
                                <form action="{{ route('asset-verifications.complete', $verification) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="w-7 h-7 bg-brand text-white rounded flex items-center justify-center hover:bg-brand-dark transition shadow-sm" title="Complete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    </button>
                                </form>
                                @endif
                                <button onclick="openDeleteModal('{{ route('asset-verifications.destroy', $verification) }}', '{{ $verification->asset->name ?? 'this verification' }}')"
                                    class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                </button>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No verifications found.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Verification Modal --}}
<div id="verificationModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">New Verification</h3>
            <button onclick="closeVerificationModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('asset-verifications.store') }}" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto">
            @csrf
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Asset <span class="text-red-500">*</span></label>
                    <select name="asset_id" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                        <option value="">Select Asset</option>
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->asset_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Location <span class="text-red-500">*</span></label>
                    <select name="location_id" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                        <option value="">Select Location</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity_verified" required min="1" value="1"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Condition <span class="text-red-500">*</span></label>
                        <select name="condition" required
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="broken">Broken</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Remark</label>
                    <textarea name="remark" rows="3" placeholder="Optional notes about this verification..."
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200 resize-none"></textarea>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Evidence Photo</label>
                    <input type="file" name="image" accept="image/*"
                        class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand file:text-white hover:file:bg-brand-dark transition cursor-pointer">
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="closeVerificationModal()"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <button type="submit"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">Submit Verification</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="deleteModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[150] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden p-6 animate__fade-in">
        <div class="text-center">
            <div class="w-14 h-14 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Delete Confirmation</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Are you sure you want to delete <strong id="deleteItemName">this item</strong>? This action cannot be undone.</p>
            <form id="deleteForm" method="POST" class="mt-6">
                @csrf @method('DELETE')
                <div class="flex items-center justify-center gap-3">
                    <button type="button" onclick="closeDeleteModal()"
                        class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                    <button type="submit"
                        class="bg-red-500 hover:bg-red-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.animate__fade-in { animation: fadeIn 0.15s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>

<script>
    function openVerificationModal() {
        document.getElementById('verificationModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeVerificationModal() {
        document.getElementById('verificationModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    function openDeleteModal(action, name) {
        document.getElementById('deleteForm').action = action;
        document.getElementById('deleteItemName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
</script>
@endsection
