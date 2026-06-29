@extends('layouts.app')
@section('title', 'Asset Register')
@section('content')
<div class="space-y-6" x-data="{ search: '' }">
    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-800 tracking-tight">Asset Register</h1>
            <p class="text-gray-500 text-sm mt-0.5">Manage and track your organization's assets.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="relative w-72">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input type="text" x-model="search" placeholder="Search Item"
                    class="w-full bg-white border border-gray-200 rounded-xl py-2.5 pl-11 pr-4 text-sm tracking-wide placeholder-gray-400 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition">
            </div>
            <button onclick="openAssetModal('create')"
                class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm flex items-center gap-2 transition">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.75 6.75V14.75M14.75 10.75H6.75M10.75 20.75C16.2728 20.75 20.75 16.2728 20.75 10.75C20.75 5.22715 16.2728 0.75 10.75 0.75C5.22715 0.75 0.75 5.22715 0.75 10.75C0.75 16.2728 5.22715 20.75 10.75 20.75Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Register Asset
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">Image</th>
                        <th class="p-4 font-semibold tracking-wide">Code</th>
                        <th class="p-4 font-semibold tracking-wide">Asset Details</th>
                        <th class="p-4 font-semibold tracking-wide">Category</th>
                        <th class="p-4 font-semibold tracking-wide">Status</th>
                        <th class="p-4 font-semibold tracking-wide">Price</th>
                        <th class="p-4 pr-5 font-semibold tracking-wide text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($assets as $asset)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition"
                        x-show="!search || '{{ $asset->name }} {{ $asset->asset_code }} {{ $asset->brand }} {{ $asset->model }} {{ $asset->category->name ?? '' }} {{ $asset->purchase_price ?? '' }}'.toLowerCase().includes(search.toLowerCase())">
                        <td class="p-4 pl-5">
                            @if($asset->image_path)
                                <div class="w-12 h-10 bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm">
                                    <img src="{{ asset('storage/'.$asset->image_path) }}" alt="{{ $asset->name }}" class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="w-12 h-10 bg-gray-100 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-400 dark:text-gray-500 text-xs">No</div>
                            @endif
                        </td>
                        <td class="p-4 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $asset->asset_code }}</td>
                        <td class="p-4">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $asset->name }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $asset->brand }} {{ $asset->model }}</p>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-gray-400 text-sm">{{ $asset->category->name ?? 'N/A' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $asset->status === 'active' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800' }}">{{ strtoupper($asset->status) }}</span>
                        </td>
                        <td class="p-4 font-medium text-gray-900 dark:text-white">{{ $asset->purchase_price ? '$'.number_format($asset->purchase_price, 2) : 'N/A' }}</td>
                        <td class="p-4 pr-5">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick="openAssetDetail({{ json_encode($asset) }})"
                                    class="w-7 h-7 bg-amber-500 text-white rounded flex items-center justify-center hover:bg-amber-600 transition shadow-sm" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                <button onclick="openAssetModal('edit', {{ json_encode($asset) }})"
                                    class="w-7 h-7 bg-brand text-white rounded flex items-center justify-center hover:bg-brand-dark transition shadow-sm" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                                </button>
                                <button onclick="openDeleteModal('{{ route('assets.destroy', $asset->id) }}', '{{ $asset->name }}')"
                                    class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No assets registered yet</p>
                                <p class="text-xs text-gray-300 dark:text-gray-600">Click "Register Asset" to add your first asset.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Asset Modal --}}
<div id="assetModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 id="modalTitle" class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">Add New Asset</h3>
            <button onclick="closeAssetModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="assetForm" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <div class="grid grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Asset Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="field_name" required placeholder="Enter asset name"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Category <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="category_id" id="field_category_id" required
                                class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm appearance-none focus:outline-none focus:border-brand text-gray-700 dark:text-gray-200 transition cursor-pointer">
                                <option value="">Choose category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400 dark:text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Brand</label>
                        <input type="text" name="brand" id="field_brand" placeholder="Enter brand"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Model</label>
                        <input type="text" name="model" id="field_model" placeholder="Enter model"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Serial Number</label>
                        <input type="text" name="serial_number" id="field_serial_number" placeholder="Enter serial number"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Status <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="status" id="field_status"
                                class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm appearance-none focus:outline-none focus:border-brand text-gray-700 dark:text-gray-200 transition cursor-pointer">
                                <option value="active">Active</option>
                                <option value="disposed">Disposed</option>
                            </select>
                            <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400 dark:text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Purchase Date</label>
                        <input type="date" name="purchase_date" id="field_purchase_date"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand text-gray-700 dark:text-gray-200 transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Purchase Price</label>
                        <input type="number" step="0.01" name="purchase_price" id="field_purchase_price" placeholder="Enter price"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Asset Image</label>
                        <input type="file" name="image" accept="image/jpeg,image/png"
                            class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand hover:file:bg-brand-100 transition cursor-pointer">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Description</label>
                    <textarea name="description" rows="3" placeholder="Input description"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200 resize-none"></textarea>
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="closeAssetModal()"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Cancel
                </button>
                <button type="submit" id="submitBtn"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">
                    Add
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Asset Detail Modal --}}
<div id="assetDetailModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 id="detailModalTitle" class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">Asset Details</h3>
            <button onclick="closeAssetDetail()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-gray-50/30 dark:bg-gray-900/30">
            <div class="flex items-start gap-6">
                <div id="detailImage" class="w-28 h-24 bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden flex-shrink-0 flex items-center justify-center text-gray-400 dark:text-gray-500 text-xs">No Image</div>
                <div class="flex-1">
                    <h4 id="detailName" class="text-xl font-bold text-gray-900 dark:text-white"></h4>
                    <p id="detailCode" class="text-sm font-mono text-gray-400 dark:text-gray-500 mt-1"></p>
                    <span id="detailStatus" class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-semibold"></span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div><p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Category</p><p id="detailCategory" class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5"></p></div>
                <div><p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Brand</p><p id="detailBrand" class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5"></p></div>
                <div><p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Model</p><p id="detailModel" class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5"></p></div>
                <div><p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Serial Number</p><p id="detailSerial" class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5"></p></div>
                <div><p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Purchase Date</p><p id="detailDate" class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5"></p></div>
                <div><p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Purchase Price</p><p id="detailPrice" class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5"></p></div>
            </div>
            <div id="detailDescWrap" class="hidden pt-3 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Description</p>
                <p id="detailDesc" class="text-gray-600 dark:text-gray-400 text-sm"></p>
            </div>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center bg-white dark:bg-gray-800">
            <button type="button" onclick="closeAssetDetail()"
                class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Close</button>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="deleteModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[150] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden p-6 animate__fade-in">
        <div class="text-center">
            <div class="w-14 h-14 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Delete Confirmation</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Are you sure you want to delete <strong id="deleteItemName">this item</strong>? This action cannot be undone.</p>
            <form id="deleteForm" method="POST" class="mt-6">
                @csrf @method('DELETE')
                <div class="flex items-center justify-center gap-3">
                    <button type="button" onclick="closeDeleteModal()"
                        class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                    <button type="submit"
                        class="bg-red-500 hover:bg-red-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.animate__slide-in-right { animation: slideInRight 0.2s ease-out; }
@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.animate__fade-in { animation: fadeIn 0.15s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>

<script>
    const modal = document.getElementById('assetModal');
    const form = document.getElementById('assetForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const methodInput = document.getElementById('formMethod');

    function openAssetModal(mode, data = null) {
        modal.classList.remove('hidden'); document.body.style.overflow = 'hidden';
        if (mode === 'edit' && data) {
            modalTitle.innerText = 'Edit Asset: ' + (data.asset_code || data.name);
            form.action = `/assets-registeration/${data.id}`;
            methodInput.value = 'PUT'; submitBtn.innerText = 'Update Asset';
            ['name','category_id','status','brand','model','serial_number','purchase_date','purchase_price'].forEach(k => {
                const f = document.getElementById('field_' + k);
                if (f) f.value = data[k] || '';
            });
        } else {
            modalTitle.innerText = 'Register New Asset';
            form.action = "{{ route('assets.store') }}";
            methodInput.value = 'POST'; submitBtn.innerText = 'Save Asset';
            form.reset();
        }
    }
    function closeAssetModal() { modal.classList.add('hidden'); document.body.style.overflow = 'auto'; }

    function openAssetDetail(data) {
        document.getElementById('detailModalTitle').textContent = data.name + ' (' + (data.asset_code || '') + ')';
        document.getElementById('detailName').textContent = data.name;
        document.getElementById('detailCode').textContent = data.asset_code || 'N/A';
        document.getElementById('detailCategory').textContent = data.category ? data.category.name : (data.category_name || 'N/A');
        document.getElementById('detailBrand').textContent = data.brand || 'N/A';
        document.getElementById('detailModel').textContent = data.model || 'N/A';
        document.getElementById('detailSerial').textContent = data.serial_number || 'N/A';
        document.getElementById('detailDate').textContent = data.purchase_date || 'N/A';
        document.getElementById('detailPrice').textContent = data.purchase_price ? '$' + parseFloat(data.purchase_price).toFixed(2) : 'N/A';
        const statusEl = document.getElementById('detailStatus');
        statusEl.textContent = (data.status || 'active').toUpperCase();
        statusEl.className = 'inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-semibold ' + (data.status === 'active' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800');
        if (data.description) {
            document.getElementById('detailDescWrap').classList.remove('hidden');
            document.getElementById('detailDesc').textContent = data.description;
        } else {
            document.getElementById('detailDescWrap').classList.add('hidden');
        }
        const imgEl = document.getElementById('detailImage');
        if (data.image_url) {
            imgEl.innerHTML = '<img src="' + data.image_url + '" class="w-full h-full object-cover">';
        } else {
            imgEl.innerHTML = 'No Image';
        }
        document.getElementById('assetDetailModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeAssetDetail() {
        document.getElementById('assetDetailModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openDeleteModal(action, name) {
        document.getElementById('deleteForm').action = action;
        document.getElementById('deleteItemName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
</script>
@endsection