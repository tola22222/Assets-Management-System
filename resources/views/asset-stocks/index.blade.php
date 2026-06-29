@extends('layouts.app')
@section('title', 'Asset Inventory Stock')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    <x-page-header
        title="Asset Inventory Stock"
        subtitle="Real-time asset distribution"
        buttonText="Add New Stock"
        buttonAction="openModal('create')"
    />

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">Asset</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Location</th>
                        <th class="p-4 font-semibold tracking-wide">Quantity</th>
                        <th class="p-4 pr-5 font-semibold tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($stocks as $stock)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5 whitespace-nowrap">{{ $stock->asset->name ?? 'Unknown' }}</td>
                        <td class="p-4 hidden md:table-cell">{{ $stock->location->name ?? 'Warehouse' }}</td>
                        <td class="p-4 font-semibold text-gray-900 dark:text-white">{{ $stock->quantity }}</td>
                        <td class="p-4 pr-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button onclick="openModal('edit', {{ json_encode($stock) }})"
                                    class="w-7 h-7 bg-amber-500 text-white rounded flex items-center justify-center hover:bg-amber-600 transition shadow-sm" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                                </button>
                                <button onclick="openDeleteModal('{{ route('asset-stocks.destroy', $stock->id) }}', '{{ $stock->asset->name ?? 'this stock' }}')"
                                    class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No asset stocks found yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('asset-stocks.partials.modal')

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
const modal = document.getElementById('assetStockModal');
const form = document.getElementById('assetStockForm');
const modalTitle = document.getElementById('assetModalTitle');
const methodField = document.getElementById('methodField');

function openModal(mode, data = null) {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if(mode === 'edit') {
        modalTitle.innerText = 'Edit Stock';
        form.action = `/asset-stocks/${data.id}`;
        methodField.innerHTML = '@method("PUT")';
        document.getElementById('asset_id').value = data.asset_id;
        document.getElementById('location_id').value = data.location_id;
        document.getElementById('quantity').value = data.quantity;
    } else {
        modalTitle.innerText = 'Add New Stock';
        form.action = "{{ route('asset-stocks.store') }}";
        methodField.innerHTML = '';
        form.reset();
    }
}

function closeModal() {
    modal.classList.add('hidden');
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
