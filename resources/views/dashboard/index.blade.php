@extends('layouts.app')

@section('title', 'System Overview')

@section('content')
<div class="space-y-8">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Asset Dashboard</h1>
            <p class="text-slate-500">Monitoring equipment support for public schools in Cambodia.</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export Report
            </button>
            <button class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition tracking-wide">
                + Register Asset
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </span>
                <span class="text-xs font-bold text-green-500 bg-green-50 px-2 py-1 rounded">+5%</span>
            </div>
            <p class="text-slate-500 text-sm font-medium">Active Schools</p>
            <h3 class="text-2xl font-bold text-slate-800">42</h3>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                </span>
            </div>
            <p class="text-slate-500 text-sm font-medium">Bicycles Tracking</p>
            <h3 class="text-2xl font-bold text-slate-800">1,240</h3>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2 bg-orange-50 text-orange-600 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </span>
            </div>
            <p class="text-slate-500 text-sm font-medium">Computer Labs</p>
            <h3 class="text-2xl font-bold text-slate-800">18</h3>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2 bg-red-50 text-red-600 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </span>
            </div>
            <p class="text-slate-500 text-sm font-medium">Maintenance Required</p>
            <h3 class="text-2xl font-bold text-slate-800">12</h3>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">Recently Added Assets</h3>
                <button class="text-indigo-600 text-sm font-bold hover:text-indigo-800">View All Assets</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-widest font-bold">
                            <th class="px-6 py-4">Asset / ID</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">School Location</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-bold text-slate-700 group-hover:text-indigo-600 transition">Honda Dream 2024</p>
                                    <p class="text-xs text-slate-400 underline italic">ID: MOT-SR-001</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">Motorcycle</td>
                            <td class="px-6 py-4 text-sm text-slate-600">Siem Reap High School</td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-1.5 text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    In Use
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-bold text-slate-700 group-hover:text-indigo-600 transition">Dell Latitude 5420</p>
                                    <p class="text-xs text-slate-400 underline italic">ID: LAP-PP-102</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">Computer</td>
                            <td class="px-6 py-4 text-sm text-slate-600">Bak Touk School</td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-1.5 text-xs font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Maintenance
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <h3 class="font-bold text-slate-800 mb-6">Support Distribution</h3>
            <div class="space-y-6">
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-medium text-slate-600">Phnom Penh</span>
                        <span class="font-bold text-slate-800">45%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full" style="width: 45%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-medium text-slate-600">Siem Reap</span>
                        <span class="font-bold text-slate-800">30%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-blue-400 h-2 rounded-full" style="width: 30%"></div>
                    </div>
                </div>
                 <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-medium text-slate-600">Battambang</span>
                        <span class="font-bold text-slate-800">25%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-400 h-2 rounded-full" style="width: 25%"></div>
                    </div>
                </div>
            </div>

            <div class="mt-8 p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                <p class="text-xs text-slate-500 text-center uppercase font-bold tracking-widest">System Message</p>
                <p class="text-sm text-slate-600 mt-2 text-center">New batch of 50 bicycles scheduled for delivery to Kampot next week.</p>
            </div>
        </div>

    </div>
</div>
@endsection