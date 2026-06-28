@extends('layouts.app')

@section('title', 'System Overview')

@section('content')
<div class="space-y-8">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Asset Dashboard</h1>
            <p class="text-gray-500 dark:text-gray-400">Monitoring equipment support for public schools in Cambodia.</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export Report
            </button>
            <a href="{{ route('assets.create') }}" class="bg-brand hover:bg-brand-dark text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:shadow-md transition tracking-wide flex items-center gap-2">
                <svg width="20" height="20" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.75 6.75V14.75M14.75 10.75H6.75M10.75 20.75C16.2728 20.75 20.75 16.2728 20.75 10.75C20.75 5.22715 16.2728 0.75 10.75 0.75C5.22715 0.75 0.75 5.22715 0.75 10.75C0.75 16.2728 5.22715 20.75 10.75 20.75Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Register Asset
            </a>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2.5 bg-brand-50 text-brand rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-900/50 dark:text-emerald-400 px-2.5 py-1 rounded-lg">+5%</span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Active Schools</p>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">42</h3>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2.5 bg-brand-50 text-brand rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Bicycles Tracking</p>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">1,240</h3>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2.5 bg-brand-50 text-brand rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Computer Labs</p>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">18</h3>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-2">
                <span class="p-2.5 bg-red-50 text-red-500 dark:bg-red-900/50 dark:text-red-400 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Maintenance Required</p>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">12</h3>
        </div>
    </div>

    {{-- Tables & Charts --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-bold text-gray-900 dark:text-white">Recently Added Assets</h3>
                <a href="{{ route('assets.index') }}" class="text-brand text-sm font-bold hover:text-brand-dark">View All Assets</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50/70 dark:bg-gray-800/70 text-gray-400 dark:text-gray-500 text-xs uppercase tracking-widest font-bold">
                            <th class="px-6 py-4">Asset / ID</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">School Location</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-bold text-gray-700 dark:text-gray-300">Honda Dream 2024</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">ID: MOT-SR-001</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Motorcycle</td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Siem Reap High School</td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-1.5 text-xs font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400 px-2.5 py-1 rounded-full w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    In Use
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-bold text-gray-700 dark:text-gray-300">Dell Latitude 5420</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">ID: LAP-PP-102</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Computer</td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Bak Touk School</td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-1.5 text-xs font-bold text-amber-600 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-400 px-2.5 py-1 rounded-full w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Maintenance
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <h3 class="font-bold text-gray-900 dark:text-white mb-6">Support Distribution</h3>
            <div class="space-y-6">
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Phnom Penh</span>
                        <span class="font-bold text-gray-900 dark:text-white">45%</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-brand h-2.5 rounded-full" style="width: 45%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Siem Reap</span>
                        <span class="font-bold text-gray-900 dark:text-white">30%</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-brand-light h-2.5 rounded-full" style="width: 30%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Battambang</span>
                        <span class="font-bold text-gray-900 dark:text-white">25%</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-emerald-400 h-2.5 rounded-full" style="width: 25%"></div>
                    </div>
                </div>
            </div>

            <div class="mt-8 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center uppercase font-bold tracking-widest">System Message</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 text-center">New batch of 50 bicycles scheduled for delivery to Kampot next week.</p>
            </div>
        </div>

    </div>
</div>
@endsection
