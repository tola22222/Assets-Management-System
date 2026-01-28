@extends('layouts.app')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-slate-800">Purchase Orders</h1>
        <button
            onclick="document.getElementById('poModal').classList.remove('hidden')"
            class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-bold">
            + Create PO
        </button>
    </div>

    {{-- Table --}}
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs font-bold text-slate-500 uppercase">
                    <th class="px-6 py-4">PO Number</th>
                    <th class="px-6 py-4">Supplier</th>
                    <th class="px-6 py-4">Total Amount</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($pos as $po)
                <tr>
                    <td class="px-6 py-4 font-bold">{{ $po->po_number }}</td>
                    <td class="px-6 py-4">{{ $po->supplier->name }}</td>
                    <td class="px-6 py-4 font-bold text-indigo-600">
                        ${{ number_format($po->total_amount, 2) }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs font-bold
                            {{ $po->status === 'Pending'
                                ? 'bg-amber-100 text-amber-700'
                                : 'bg-emerald-100 text-emerald-700' }}">
                            {{ $po->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button
                            onclick="viewDetails({{ $po->id }})"
                            class="text-indigo-600 hover:text-indigo-900 font-bold text-sm">
                            View Details
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- DETAILS MODAL --}}
<div id="detailModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 shadow-xl relative">
        <button
            onclick="document.getElementById('detailModal').classList.add('hidden')"
            class="absolute top-4 right-4 text-2xl text-slate-400 hover:text-slate-600">
            &times;
        </button>

        <div id="detailContent" class="py-10 text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
        </div>

        <div class="mt-6 flex justify-end">
            <button
                onclick="document.getElementById('detailModal').classList.add('hidden')"
                class="bg-slate-100 px-6 py-2 rounded-lg font-bold text-slate-600">
                Close
            </button>
        </div>
    </div>
</div>

{{-- CREATE PO MODAL --}}
<div id="poModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-4xl p-6 shadow-xl max-h-[90vh] overflow-y-auto">

        <h2 class="text-xl font-bold mb-4">New Purchase Order</h2>

        <form action="{{ route('purchase-orders.store') }}" method="POST">
            @csrf

            {{-- Supplier --}}
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Supplier</label>
                    <select name="supplier_id" class="w-full mt-1 p-2 border rounded-lg" required>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">PO Number</label>
                    <input
                        type="text"
                        disabled
                        value="Auto Generated"
                        class="w-full mt-1 p-2 bg-slate-100 border rounded-lg text-slate-400">
                </div>
            </div>

            {{-- Items --}}
            <div class="flex justify-between items-center border-b pb-2 mb-3">
                <h3 class="font-bold text-slate-700">Order Items</h3>
                <button type="button" onclick="addItemRow()" class="text-indigo-600 text-sm font-bold">
                    + Add Item
                </button>
            </div>

            <div id="items-container" class="space-y-3">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <div class="col-span-5">
                        <select name="items[0][product_id]" class="w-full p-2 border rounded-lg" required>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-3">
                        <input type="number" name="items[0][quantity]" min="1" required class="w-full p-2 border rounded-lg" placeholder="Qty">
                    </div>
                    <div class="col-span-3">
                        <input type="number" step="0.01" name="items[0][price]" min="0" required class="w-full p-2 border rounded-lg" placeholder="Price">
                    </div>
                    <div class="col-span-1"></div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-2 mt-6 border-t pt-4">
                <button type="button" onclick="document.getElementById('poModal').classList.add('hidden')" class="px-4 py-2 text-slate-500">
                    Cancel
                </button>
                <button type="submit" class="bg-indigo-600 text-white px-8 py-2 rounded-lg font-bold">
                    Create PO
                </button>
            </div>
        </form>
    </div>
</div>

{{-- JS --}}
<script>
    let itemCount = 1;

    function addItemRow() {
        const container = document.getElementById('items-container');

        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center';
        row.id = `row-${itemCount}`;

        row.innerHTML = `
            <div class="col-span-5">
                <select name="items[${itemCount}][product_id]" class="w-full p-2 border rounded-lg" required>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-3">
                <input type="number" name="items[${itemCount}][quantity]" min="1" required class="w-full p-2 border rounded-lg" placeholder="Qty">
            </div>
            <div class="col-span-3">
                <input type="number" step="0.01" name="items[${itemCount}][price]" min="0" required class="w-full p-2 border rounded-lg" placeholder="Price">
            </div>
            <div class="col-span-1 text-center">
                <button type="button" onclick="removeItemRow(${itemCount})" class="text-red-500 font-bold">
                    ✕
                </button>
            </div>
        `;

        container.appendChild(row);
        itemCount++;
    }

    function removeItemRow(id) {
        document.getElementById(`row-${id}`).remove();
    }

    async function viewDetails(id) {
        const modal = document.getElementById('detailModal');
        const content = document.getElementById('detailContent');

        modal.classList.remove('hidden');
        content.innerHTML = '<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>';

        const res = await fetch(`/purchase-orders/${id}`);
        content.innerHTML = await res.text();
    }
</script>
@endsection
