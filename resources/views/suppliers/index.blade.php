@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl shadow-sm">
            {{ session('success') }}
        </div>
    @endif

     <x-page-header
        title="Suppliers Management"
        subtitle="Manage your asset vendors and contacts."
        buttonText="Add Supplier"
        buttonAction="document.getElementById('addModal').classList.remove('hidden')"
    />

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Supplier Name</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Phone</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Address</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($suppliers as $supplier)
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4 font-bold text-slate-700">{{ $supplier->name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $supplier->phone ?? 'N/A' }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 truncate max-w-xs">{{ $supplier->address ?? 'N/A' }}</td>
                    <td class="px-6 py-4 text-right flex justify-end gap-4">
                        <button onclick="openEditModal({{ $supplier }})"
                                class="text-indigo-600 hover:text-indigo-900 text-sm font-bold transition">
                            Edit
                        </button>

                        <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:text-red-700 text-sm font-bold transition">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-10 text-center text-slate-400">No suppliers found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4 text-slate-800">New Supplier</h2>
        <form action="{{ route('suppliers.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Name</label>
                <input type="text" name="name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none" required>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Phone Number</label>
                <input type="text" name="phone" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Address</label>
                <textarea name="address" rows="3" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4 text-slate-800">Edit Supplier</h2>
        <form id="editForm" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Name</label>
                <input type="text" name="name" id="edit_name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none" required>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Phone Number</label>
                <input type="text" name="phone" id="edit_phone" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Address</label>
                <textarea name="address" id="edit_address" rows="3" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold">Update Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(supplier) {
        const modal = document.getElementById('editModal');
        const form = document.getElementById('editForm');
        form.action = `/suppliers/${supplier.id}`;
        document.getElementById('edit_name').value = supplier.name;
        document.getElementById('edit_phone').value = supplier.phone || '';
        document.getElementById('edit_address').value = supplier.address || '';
        modal.classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
@endsection
