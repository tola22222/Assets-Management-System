@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Products</h1>
            <p class="text-slate-500 text-sm">Define and manage item types for inventory.</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-indigo-700 transition shadow-sm">
            + New Product
        </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Product Name</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Category</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Type</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($products as $product)
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4 font-bold text-slate-700">{{ $product->name }}</td>
                    <td class="px-6 py-4">
                        <span class="text-sm text-slate-600 bg-slate-100 px-2 py-1 rounded">
                            {{ $product->category->name }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($product->asset_type === 'Fixed')
                            <span class="px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-bold uppercase tracking-wider">
                                Fixed
                            </span>
                        @else
                            <span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-lg text-xs font-bold uppercase tracking-wider">
                                Consumable
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right flex justify-end gap-4">
                        <button onclick="openEditModal({{ $product }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-bold">Edit</button>
                        <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Delete this product?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-bold">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-100">
            {{ $products->links() }}
        </div>
    </div>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4">New Product</h2>
        <form action="{{ route('products.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Category</label>
                <select name="category_id" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
                    <option value="">Select Category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Product Name</label>
                <input type="text" name="name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required placeholder="e.g., MacBook Air M2">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Asset Type</label>
                <select name="asset_type" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
                    <option value="Fixed">Fixed Asset</option>
                    <option value="Consumable">Consumable</option>
                </select>
            </div>
            <div class="flex gap-2 justify-end pt-4">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold">Save Product</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4">Edit Product</h2>
        <form id="editForm" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Category</label>
                <select name="category_id" id="edit_category_id" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Product Name</label>
                <input type="text" name="name" id="edit_name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Asset Type</label>
                <select name="asset_type" id="edit_asset_type" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
                    <option value="Fixed">Fixed Asset</option>
                    <option value="Consumable">Consumable</option>
                </select>
            </div>
            <div class="flex gap-2 justify-end pt-4">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold">Update Product</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(product) {
        document.getElementById('editForm').action = `/products/${product.id}`;
        document.getElementById('edit_name').value = product.name;
        document.getElementById('edit_category_id').value = product.category_id;
        document.getElementById('edit_asset_type').value = product.asset_type;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
@endsection