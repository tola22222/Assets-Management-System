<div class="space-y-6">
    <div class="flex justify-between items-start border-b border-slate-100 pb-4">
        <div>
            <h3 class="text-lg font-bold text-slate-800">{{ $po->po_number }}</h3>
            <p class="text-sm text-slate-500">Date: {{ $po->created_at->format('M d, Y') }}</p>
        </div>
        <div class="text-right">
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $po->status == 'Pending' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                {{ $po->status }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <p class="text-slate-400 font-bold uppercase text-[10px]">Supplier</p>
            <p class="font-bold text-slate-700">{{ $po->supplier->name }}</p>
            <p class="text-slate-500">{{ $po->supplier->phone }}</p>
        </div>
        <div class="text-right">
            <p class="text-slate-400 font-bold uppercase text-[10px]">Total Amount</p>
            <p class="text-xl font-black text-indigo-600">${{ number_format($po->total_amount, 2) }}</p>
        </div>
    </div>

    <div class="border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-2 font-bold text-slate-600">Product</th>
                    <th class="px-4 py-2 font-bold text-slate-600 text-center">Qty</th>
                    <th class="px-4 py-2 font-bold text-slate-600 text-right">Price</th>
                    <th class="px-4 py-2 font-bold text-slate-600 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($po->items as $item)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-bold text-slate-700">{{ $item->product->name }}</p>
                        <p class="text-[10px] text-slate-400">{{ $item->product->category->name }}</p>
                    </td>
                    <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                    <td class="px-4 py-3 text-right">${{ number_format($item->price, 2) }}</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-700">
                        ${{ number_format($item->quantity * $item->price, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>