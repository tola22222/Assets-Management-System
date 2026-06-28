@extends('layouts.app')
@section('title', 'Manage Asset Categories')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    <x-page-header
        title="Asset Categories"
        subtitle="Classify and organize your hardware, software, and tools."
        buttonText="Create New Category"
        buttonAction="openModal('create')"
    />

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">Category Name</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Short Name</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Description</th>
                        <th class="p-4 pr-5 font-semibold tracking-wide text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($categories as $category)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5 font-medium text-gray-900 dark:text-white">{{ $category->name }}</td>
                        <td class="p-4 hidden md:table-cell text-gray-500 dark:text-gray-400">{{ $category->short_name }}</td>
                        <td class="p-4 hidden md:table-cell text-gray-500 dark:text-gray-400">{{ Str::limit($category->description, 80) ?: '---' }}</td>
                        <td class="p-4 pr-5">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick="openModal('edit', {{ json_encode($category) }})"
                                    class="w-7 h-7 bg-brand text-white rounded flex items-center justify-center hover:bg-brand-dark transition shadow-sm" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                                </button>
                                <form action="{{ route('categories.destroy', $category->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete this category?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No categories found yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="categoryModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 id="modalTitle" class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">Add New Category</h3>
            <button onclick="closeModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="categoryForm" method="POST" class="flex-1 overflow-y-auto">
            @csrf
            <div id="methodField"></div>
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="cat_name" placeholder="e.g. Laptops, Office Furniture" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Short Name <span class="text-red-500">*</span></label>
                    <input type="text" name="short_name" id="cat_short_name" placeholder="e.g. LAPTOP, FURNITURE" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Description</label>
                    <textarea name="description" id="cat_desc" rows="4" placeholder="Brief details about this category..."
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200 resize-none"></textarea>
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="closeModal()"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <button type="submit"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<style>
.animate__slide-in-right { animation: slideInRight 0.2s ease-out; }
@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<script>
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const modalTitle = document.getElementById('modalTitle');
    const methodField = document.getElementById('methodField');

    function openModal(mode, data = null) {
        modal.classList.remove('hidden'); document.body.style.overflow = 'hidden';
        if (mode === 'edit') {
            modalTitle.innerText = 'Edit Category';
            form.action = `/asset-categories/${data.id}`;
            methodField.innerHTML = '@method("PUT")';
            document.getElementById('cat_name').value = data.name;
            document.getElementById('cat_short_name').value = data.short_name;
            document.getElementById('cat_desc').value = data.description || '';
        } else {
            modalTitle.innerText = 'Add New Category';
            form.action = "{{ route('categories.store') }}";
            methodField.innerHTML = '';
            form.reset();
        }
    }

    function closeModal() { modal.classList.add('hidden'); document.body.style.overflow = 'auto'; }

    setTimeout(() => {
        const alert = document.getElementById('alert-success');
        if(alert) alert.classList.add('opacity-0');
    }, 5000);
</script>
@endsection
