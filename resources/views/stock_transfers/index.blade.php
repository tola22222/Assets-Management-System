@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Stock Transfers</h1>
            <p class="text-slate-500 text-sm">Move assets from HQ to specific schools.</p>
        </div>
        <button onclick="document.getElementById('transferModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-indigo-700 transition">
            + New Transfer
        </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Transfer Date</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Destination School</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Transferred By</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Status</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($transfers as $transfer)
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4 text-sm font-medium text-slate-700">
                        {{ \Carbon\Carbon::parse($transfer->transfer_date)->format('M d, Y') }}
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-800">{{ $transfer->school->name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $transfer->user->name }}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $transfer->status == 'Approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $transfer->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right flex justify-end gap-3">
                        <button onclick="viewTransfer({{ $transfer->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold text-sm">Details</button>
                        @if($transfer->status === 'Pending')
                        <form action="{{ route('stock-transfers.approve', $transfer) }}" method="POST" onsubmit="return confirm('Approve this transfer and update inventory?')">
                            @csrf
                            <button class="text-emerald-600 hover:text-emerald-800 font-bold text-sm">Approve</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div id="transferModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-4xl p-6 shadow-xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold mb-4 text-slate-800">Initiate New Stock Transfer</h2>
        <form action="{{ route('stock-transfers.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Destination School</label>
                    <select name="school_id" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
                        <option value="">-- Select School --</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}">{{ $school->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Transfer Date</label>
                    <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
            </div>

            <div class="flex justify-between items-center mb-2 border-b border-slate-100 pb-2">
                <h3 class="font-bold text-slate-700">Products to Transfer</h3>
                <button type="button" onclick="addTransferRow()" class="text-indigo-600 text-sm font-bold hover:underline">+ Add Product</button>
            </div>

            <div id="transfer-items-container" class="space-y-3">
                <div class="grid grid-cols-12 gap-3 items-end">
                    <div class="col-span-8">
                        <label class="text-[10px] text-slate-400 font-bold uppercase">Product</label>
                        <select name="items[0][product_id]" class="w-full p-2 border border-slate-200 rounded-lg shadow-sm" required>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->asset_type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-3">
                        <label class="text-[10px] text-slate-400 font-bold uppercase">Quantity</label>
                        <input type="number" name="items[0][quantity]" class="w-full p-2 border border-slate-200 rounded-lg shadow-sm" min="1" required placeholder="0">
                    </div>
                </div>
            </div>

            <div class="flex gap-2 mt-8 justify-end">
                <button type="button" onclick="document.getElementById('transferModal').classList.add('hidden')" class="px-4 py-2 text-slate-500 font-medium">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-8 py-2 rounded-lg font-bold shadow-lg hover:bg-indigo-700 transition">Confirm Transfer</button>
            </div>
        </form>
    </div>
</div>

<script>
    let rowIdx = 1;
    function addTransferRow() {
        const container = document.getElementById('transfer-items-container');
        const div = document.createElement('div');
        div.className = "grid grid-cols-12 gap-3 items-center animate-in slide-in-from-top-1 duration-200";
        div.id = `row-${rowIdx}`;
        div.innerHTML = `
            <div class="col-span-8">
                <select name="items[${rowIdx}][product_id]" class="w-full p-2 border border-slate-200 rounded-lg shadow-sm" required>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->asset_type }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-3">
                <input type="number" name="items[${rowIdx}][quantity]" class="w-full p-2 border border-slate-200 rounded-lg shadow-sm" min="1" required placeholder="0">
            </div>
            <div class="col-span-1 text-center">
                <button type="button" onclick="document.getElementById('row-${rowIdx}').remove()" class="text-red-400 hover:text-red-600 transition">&times;</button>
            </div>
        `;
        container.appendChild(div);
        rowIdx++;
    }
</script>
@endsection