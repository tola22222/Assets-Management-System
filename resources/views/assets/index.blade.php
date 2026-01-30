@extends('layouts.app')

@section('title', 'Asset Inventory')

@section('content')
    <div class="max-w-[1600px] mx-auto p-6 space-y-6">
        <div class="flex justify-between items-center bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Asset Inventory</h1>
                <p class="text-slate-500 text-sm font-medium">Manage and track your organization's assets.</p>
            </div>
            <button onclick="openAssetModal('create')"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round" />
                </svg>
                Register New Asset
            </button>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Asset Details</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Category</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Condition/Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Purchase Info</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($assets as $asset)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-xs font-bold text-indigo-600">{{ $asset->asset_code }}</div>
                                <div class="font-bold text-slate-800">{{ $asset->name }}</div>
                                <div class="text-xs text-slate-400">{{ $asset->brand }} {{ $asset->model }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-slate-600">{{ $asset->category->name ?? 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $asset->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ strtoupper($asset->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">
                                <div>{{ $asset->purchase_date }}</div>
                                <div class="font-bold text-slate-700">${{ number_format($asset->purchase_price, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button onclick="openAssetModal('edit', {{ json_encode($asset) }})"
                                    class="text-indigo-600 hover:underline font-bold text-sm">Edit</button>
                                <form action="{{ route('assets.destroy', $asset->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        onclick="return confirm('Are you sure you want to delete this asset? This action cannot be undone.')"
                                        class="inline-flex items-center px-3 py-2 text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 hover:text-red-700 rounded-xl transition-all duration-200 group">

                                        <svg class="w-4 h-4 mr-1.5 transition-transform group-hover:scale-110"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>

                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-400">No assets registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="assetModal"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-[100] flex items-center justify-center p-4">
        <div
            class="bg-slate-50 rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col border border-white/20">
            <div class="px-8 py-6 bg-white border-b border-slate-200 flex justify-between items-center">
                <h2 id="modalTitle" class="text-2xl font-black text-slate-900 tracking-tight">Register New Asset</h2>
                <button onclick="closeAssetModal()"
                    class="p-2 hover:bg-slate-100 rounded-full text-slate-400 transition-colors">&times;</button>
            </div>

            <form id="assetForm" method="POST" class="overflow-y-auto p-8 space-y-8">
                @csrf
                <div id="methodField"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-6">
                        <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">General Info</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Asset Code *</label>
                                <input type="text" name="asset_code" id="field_asset_code" required
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Asset Name *</label>
                                <input type="text" name="name" id="field_name" required
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Category *</label>
                                <select name="category_id" id="field_category_id" required
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Specifications</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Brand</label>
                                <input type="text" name="brand" id="field_brand"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Model</label>
                                <input type="text" name="model" id="field_model"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Status</label>
                                <select name="status" id="field_status"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                                    <option value="active">Active</option>
                                    <option value="disposed">Disposed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-200">
                    <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500 mb-4">Financials & Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Serial Number</label>
                            <input type="text" name="serial_number" id="field_serial_number"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Purchase Date</label>
                            <input type="date" name="purchase_date" id="field_purchase_date"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Purchase Price</label>
                            <input type="number" step="0.01" name="purchase_price" id="field_purchase_price"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                        </div>
                    </div>
                </div>
            </form>

            <div class="px-8 py-6 bg-white border-t border-slate-200 flex justify-end gap-3">
                <button onclick="closeAssetModal()"
                    class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-50 rounded-xl transition-all">Discard</button>
                <button type="submit" form="assetForm"
                    class="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-100 transition-all">Save
                    Asset</button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('assetModal');
        const form = document.getElementById('assetForm');
        const modalTitle = document.getElementById('modalTitle');
        const methodField = document.getElementById('methodField');

        function openAssetModal(mode, data = null) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            if (mode === 'edit') {
                modalTitle.innerText = 'Edit Asset: ' + data.asset_code;
                form.action = `/assets-registeration/${data.id}`; // Match your Route::resource
                methodField.innerHTML = '@method('PUT')';

                // Fill Fields
                document.getElementById('field_asset_code').value = data.asset_code;
                document.getElementById('field_name').value = data.name;
                document.getElementById('field_category_id').value = data.category_id;
                document.getElementById('field_brand').value = data.brand || '';
                document.getElementById('field_model').value = data.model || '';
                document.getElementById('field_status').value = data.status;
                document.getElementById('field_serial_number').value = data.serial_number || '';
                document.getElementById('field_purchase_date').value = data.purchase_date || '';
                document.getElementById('field_purchase_price').value = data.purchase_price || '';
            } else {
                modalTitle.innerText = 'Register New Asset';
                form.action = "{{ route('assets.store') }}";
                methodField.innerHTML = '';
                form.reset();
            }
        }

        function closeAssetModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    </script>
@endsection
