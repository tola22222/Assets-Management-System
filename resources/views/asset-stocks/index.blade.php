@extends('layouts.app')

@section('content')
<div x-data="{ 
    open: false, 
    editMode: false, 
    action: '',
    form: { id: '', asset_id: '', location_id: '', quantity: 0 },
    initForm(data = null) {
        if(data) {
            this.editMode = true;
            this.action = '/asset-stocks/' + data.id;
            this.form = { ...data };
        } else {
            this.editMode = false;
            this.action = '{{ route('asset-stocks.store') }}';
            this.form = { id: '', asset_id: '', location_id: '', quantity: 0 };
        }
        this.open = true;
    }
}" class="p-6 max-w-7xl mx-auto">

    <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Inventory Stock</h1>
            <p class="text-slate-500 font-medium">Real-time asset distribution across locations.</p>
        </div>
        <button @click="initForm()" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-bold shadow-xl shadow-indigo-200 hover:bg-indigo-700 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add New Stock
        </button>
    </div>

    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] uppercase font-black text-slate-400">Asset Detail</th>
                    <th class="px-6 py-4 text-[10px] uppercase font-black text-slate-400">Location</th>
                    <th class="px-6 py-4 text-[10px] uppercase font-black text-slate-400">In Stock</th>
                    <th class="px-6 py-4 text-[10px] uppercase font-black text-slate-400 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($stocks as $stock)
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold">
                                {{ substr($stock->asset->name ?? 'A', 0, 1) }}
                            </div>
                            <div>
                                <p class="font-bold text-slate-800">{{ $stock->asset->name ?? 'Unknown Asset' }}</p>
                                <p class="text-xs text-slate-400">ID: #{{ $stock->asset_id }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"/></svg>
                            {{ $stock->location->name ?? 'Warehouse' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-black {{ $stock->quantity < 5 ? 'text-red-500' : 'text-slate-700' }}">
                                {{ $stock->quantity }}
                            </span>
                            @if($stock->quantity < 5)
                                <span class="text-[9px] font-black bg-red-100 text-red-600 px-1.5 py-0.5 rounded uppercase">Low</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="initForm({{ json_encode($stock) }})" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-white rounded-lg shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <form action="{{ route('asset-stocks.destroy', $stock->id) }}" method="POST" onsubmit="return confirm('Delete stock record?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-slate-400 hover:text-red-600 hover:bg-white rounded-lg shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('asset-stocks.partials.modal')
</div>
@endsection