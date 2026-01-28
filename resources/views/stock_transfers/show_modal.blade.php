<div class="space-y-6">
    <div class="flex justify-between items-center border-b pb-4">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Transfer #{{ $transfer->id }}</h2>
            <p class="text-xs text-slate-500">Authorized by: {{ $transfer->user->name }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs font-bold text-slate-400 uppercase">Destination</p>
            <p class="font-bold text-indigo-600 text-lg">{{ $transfer->school->name }}</p>
        </div>
    </div>

    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex justify-between">
        <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase">Transfer Date</p>
            <p class="text-sm font-bold text-slate-700">{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') }}</p>
        </div>
        <div class="text-right">
            <p class="text-[10px] text-slate-400 font-bold uppercase">Status</p>
            <p class="text-sm font-bold {{ $transfer->status == 'Approved' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $transfer->status }}</p>
        </div>
    </div>

    <table class="w-full text-left text-sm border border-slate-100 rounded-lg">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-2 font-bold text-slate-600">Product Name</th>
                <th class="px-4 py-2 font-bold text-slate-600 text-center">Qty Sent</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($transfer->items as $item)
            <tr>
                <td class="px-4 py-3 text-slate-700">{{ $item->product->name }}</td>
                <td class="px-4 py-3 text-center font-bold text-indigo-600">{{ $item->quantity }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>