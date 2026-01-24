@extends('layouts.app')

@section('title', 'Asset Categories')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Asset Categories</h1>
        <p class="text-slate-500 text-sm">Define types of equipment (e.g., Transport, IT, Furniture).</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm sticky top-8">
                <h3 class="font-bold text-slate-800 mb-4 text-lg">Create New Category</h3>
                <form action="#" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Category Name</label>
                        <input type="text" placeholder="e.g. Bicycles" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                        <textarea rows="3" placeholder="Description of items in this category..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Prefix Code (Optional)</label>
                        <input type="text" placeholder="e.g. BIKE-" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        <p class="text-[10px] text-slate-400 mt-1">Used for automatic Asset ID generation.</p>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition">
                        Save Category
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-widest font-bold">
                        <tr>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Total Assets</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-700">Bicycles</div>
                                <div class="text-xs text-slate-400">School student transport support</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-lg text-xs font-bold">1,240 Units</span>
                            </td>
                            <td class="px-6 py-4 text-emerald-600 font-semibold text-sm">Active</td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-indigo-600 font-medium text-sm px-2">Edit</button>
                                <button class="text-slate-400 hover:text-red-600 font-medium text-sm px-2">Delete</button>
                            </td>
                        </tr>

                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-700">Motorcycles</div>
                                <div class="text-xs text-slate-400">Staff and teacher field support</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-lg text-xs font-bold">85 Units</span>
                            </td>
                            <td class="px-6 py-4 text-emerald-600 font-semibold text-sm">Active</td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-indigo-600 font-medium text-sm px-2">Edit</button>
                                <button class="text-slate-400 hover:text-red-600 font-medium text-sm px-2">Delete</button>
                            </td>
                        </tr>

                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-700">IT Equipment</div>
                                <div class="text-xs text-slate-400">Laptops, desktops, and projectors</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-lg text-xs font-bold">156 Units</span>
                            </td>
                            <td class="px-6 py-4 text-emerald-600 font-semibold text-sm">Active</td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-slate-400 hover:text-indigo-600 font-medium text-sm px-2">Edit</button>
                                <button class="text-slate-400 hover:text-red-600 font-medium text-sm px-2">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection