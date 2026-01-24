@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Purchase Orders</h1>
            <p class="text-slate-500 text-sm">Manage procurement of new equipment for school support.</p>
        </div>
        <button class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition">
            + New Purchase Order
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium">Pending Orders</p>
                <h4 class="text-xl font-bold text-slate-800">12</h4>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium">Completed This Month</p>
                <h4 class="text-xl font-bold text-slate-800">28</h4>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium">Total Procurement</p>
                <h4 class="text-xl font-bold text-slate-800">$14,250</h4>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-widest font-bold">
                <tr>
                    <th class="px-6 py-4">PO Number</th>
                    <th class="px-6 py-4">Vendor / Supplier</th>
                    <th class="px-6 py-4">Items</th>
                    <th class="px-6 py-4">Order Date</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-6 py-4 font-bold text-slate-700">#PO-2026-001</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-slate-700">Phnom Penh Cycle Import</div>
                        <div class="text-xs text-slate-400">VAT: 102293881</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-slate-600">50x Japanese Bicycles</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">Jan 18, 2026</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full uppercase">Pending</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button class="p-2 text-slate-400 hover:text-indigo-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                            <button class="p-2 text-slate-400 hover:text-emerald-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg></button>
                        </div>
                    </td>
                </tr>
                
                <tr class="hover:bg-slate-50 transition text-slate-400 italic">
                    <td class="px-6 py-4">#PO-2026-002</td>
                    <td class="px-6 py-4 font-semibold text-slate-600">K-Computers Ltd.</td>
                    <td class="px-6 py-4">15x Laptops</td>
                    <td class="px-6 py-4">Jan 12, 2026</td>
                    <td class="px-6 py-4 text-emerald-600">
                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full uppercase">Received</span>
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