@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Categories</h1>
            <p class="text-slate-500 text-sm">Organize your assets by type (e.g., Electronics, Furniture).</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-indigo-700 transition shadow-sm">
            + Add Category
        </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Category Name</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Created Date</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($categories as $category)
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4">
                        <span class="font-bold text-slate-700">{{ $category->name }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500">
                        {{ $category->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-6 py-4 text-right flex justify-end gap-4">
                        <button onclick="openEditModal({{ $category }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-bold">Edit</button>
                        
                        <form action="{{ route('categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete this category?')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:text-red-700 text-sm font-bold">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-100">
            {{ $categories->links() }}
        </div>
    </div>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4">New Category</h2>
        <form action="{{ route('categories.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="text-xs font-bold text-slate-500 uppercase">Category Name</label>
                <input type="text" name="name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. IT Equipment" required>
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4">Edit Category</h2>
        <form id="editForm" method="POST">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="text-xs font-bold text-slate-500 uppercase">Category Name</label>
                <input type="text" name="name" id="edit_name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500" required>
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(category) {
        document.getElementById('editForm').action = `/categories/${category.id}`;
        document.getElementById('edit_name').value = category.name;
        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
@endsection