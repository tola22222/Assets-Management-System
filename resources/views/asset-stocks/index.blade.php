@extends('layouts.app')

@section('title', 'Asset Inventory Stock')

@section('content')
<div class="space-y-6">

    {{-- SUCCESS ALERT --}}
    @if(session('success'))
    <div id="alert-success" class="flex items-center p-4 mb-4 text-emerald-800 rounded-lg bg-emerald-50 border border-emerald-200 transition-opacity duration-500">
        <svg class="flex-shrink-0 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
        </svg>
        <div class="ms-3 text-sm font-medium">{{ session('success') }}</div>
        <button type="button" class="ms-auto -mx-1.5 -my-1.5 bg-emerald-50 text-emerald-500 rounded-lg p-1.5 hover:bg-emerald-200 inline-flex items-center justify-center h-8 w-8" onclick="this.parentElement.remove()">
            <span class="sr-only">Close</span>
            <svg class="w-3 h-3" fill="none" viewBox="0 0 14 14" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
        </button>
    </div>
    @endif

    {{-- PAGE HEADER --}}
    <x-page-header
        title="Asset Inventory Stock"
        subtitle="Real-time asset distribution"
        buttonText="Add New Stock"
        buttonAction="openModal('create')"
    />

    {{-- TABLE --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Asset</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Location</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Quantity</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($stocks as $stock)
                    <tr class="group hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 whitespace-nowrap">{{ $stock->asset->name ?? 'Unknown' }}</td>
                        <td class="px-6 py-4">{{ $stock->location->name ?? 'Warehouse' }}</td>
                        <td class="px-6 py-4 font-bold">{{ $stock->quantity }}</td>
                        <td class="px-6 py-4 text-right flex justify-end gap-2">
                            <button onclick="openModal('edit', {{ json_encode($stock) }})"
                                class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                                title="Edit">
                                Edit
                            </button>

                            <form action="{{ route('asset-stocks.destroy', $stock->id) }}" method="POST">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    onclick="return confirm('Delete this stock?')"
                                    class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                    title="Delete">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-medium">
                            No asset stocks found yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL --}}
@include('asset-stocks.partials.modal')

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
</script>

@endsection
