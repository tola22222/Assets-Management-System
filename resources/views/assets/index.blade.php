@extends('layouts.app')

@section('title', 'Asset Inventory')

@section('content')
    <div class="space-y-8">
        <x-page-header title="Asset Registration" subtitle="Manage and track your organization's assets."
            buttonText="Register New Asset" buttonAction="openAssetModal('create')" />

        {{-- Your table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Asset ID</th>
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
                            <td class="px-6 py-4 font-mono text-sm text-slate-600">{{ $asset->asset_code }}</td>
                            <td class="px-6 py-4">
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

    {{-- Include Modal --}}
    @include('assets._modal')

    {{-- Scripts --}}
    <script>
        const modal = document.getElementById('assetModal');
        const form = document.getElementById('assetForm');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        const methodInput = document.getElementById('formMethod');

        function openAssetModal(mode, data = null) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            if (mode === 'edit' && data) {
                modalTitle.innerText = 'Edit Asset: ' + (data.asset_code || data.name);
                // Update the form action URL (assuming standard resource naming)
                form.action = `/assets-registeration/${data.id}`;
                methodInput.value = 'PUT';
                submitBtn.innerText = 'Update Asset';

                // Fill fields using the ID mapping
                const fields = [
                    'name', 'category_id', 'status', 'brand',
                    'model', 'serial_number', 'purchase_date', 'purchase_price'
                ];

                fields.forEach(key => {
                    const field = document.getElementById(`field_${key}`);
                    if (field) {
                        field.value = data[key] || '';
                    }
                });
            } else {
                modalTitle.innerText = 'Register New Asset';
                form.action = "{{ route('assets.store') }}";
                methodInput.value = 'POST';
                submitBtn.innerText = 'Save Asset';
                form.reset();
            }
        }

        function closeAssetModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    </script>
@endsection
