@extends('layouts.app')

@section('title', 'Inventory Management')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Inventory Management</h1>
            <p class="text-slate-500 dark:text-gray-400 text-sm">Manage procurement of new equipment for school support.</p>
        </div>
        <button class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition">
            + New Inventory Item
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-slate-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
                <x-icon name="clock-outline" class="w-6 h-6"/>
            </div>
            <div>
                <p class="text-sm text-slate-500 dark:text-gray-400 font-medium">Pending Orders</p>
                <h4 class="text-xl font-bold text-slate-800 dark:text-white">12</h4>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-slate-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                <x-icon name="checkmark-circle-outline" class="w-6 h-6"/>
            </div>
            <div>
                <p class="text-sm text-slate-500 dark:text-gray-400 font-medium">Completed This Month</p>
                <h4 class="text-xl font-bold text-slate-800 dark:text-white">28</h4>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-slate-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                <x-icon name="credit-card-outline" class="w-6 h-6"/>
            </div>
            <div>
                <p class="text-sm text-slate-500 dark:text-gray-400 font-medium">Total Procurement</p>
                <h4 class="text-xl font-bold text-slate-800 dark:text-white">$14,250</h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-100 dark:border-gray-700 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 dark:bg-gray-900 text-slate-400 dark:text-gray-500 text-[11px] uppercase tracking-widest font-bold">
                <tr>
                    <th class="px-6 py-4">PO Number</th>
                    <th class="px-6 py-4">Vendor / Supplier</th>
                    <th class="px-6 py-4">Items</th>
                    <th class="px-6 py-4">Order Date</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-gray-800">
                <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50 transition">
                    <td class="px-6 py-4 font-bold text-slate-700 dark:text-gray-300">#PO-2026-001</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-slate-700 dark:text-gray-300">Phnom Penh Cycle Import</div>
                        <div class="text-xs text-slate-400 dark:text-gray-500">VAT: 102293881</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-slate-600 dark:text-gray-400">50x Japanese Bicycles</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-gray-400">Jan 18, 2026</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 text-xs font-bold rounded-full uppercase">Pending</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button class="p-2 text-slate-400 dark:text-gray-500 hover:text-indigo-600"><x-icon name="eye-outline" class="w-5 h-5"/></button>
                            <button class="p-2 text-slate-400 dark:text-gray-500 hover:text-emerald-600"><x-icon name="checkmark-circle-outline" class="w-5 h-5"/></button>
                        </div>
                    </td>
                </tr>
                
                <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50 transition text-slate-400 dark:text-gray-500 italic">
                    <td class="px-6 py-4">#PO-2026-002</td>
                    <td class="px-6 py-4 font-semibold text-slate-600 dark:text-gray-400">K-Computers Ltd.</td>
                    <td class="px-6 py-4">15x Laptops</td>
                    <td class="px-6 py-4">Jan 12, 2026</td>
                    <td class="px-6 py-4 text-emerald-600">
                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 text-xs font-bold rounded-full uppercase">Received</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-xs font-medium">Inventory Updated</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection