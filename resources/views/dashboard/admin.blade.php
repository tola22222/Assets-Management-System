@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('content')
<div class="space-y-6 sm:space-y-8">
    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    {{-- Welcome Card --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand via-brand-dark to-emerald-800 text-white">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/4"></div>
        <div class="absolute bottom-0 left-1/3 w-48 h-48 bg-white/5 rounded-full translate-y-1/2"></div>
        <div class="relative z-10 p-5 sm:p-7 lg:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold">Welcome back, {{ Auth::user()->name ?? 'Admin' }}!</h1>
                    <p class="text-emerald-100 text-sm sm:text-base mt-1">System overview and asset management at a glance.</p>
                </div>
                <a href="{{ route('assets.create') }}" class="inline-flex items-center gap-2 bg-white dark:bg-gray-800 text-brand dark:text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg hover:bg-emerald-50 dark:hover:bg-gray-700 transition-all">
                    <svg width="20" height="20" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.75 6.75V14.75M14.75 10.75H6.75M10.75 20.75C16.2728 20.75 20.75 16.2728 20.75 10.75C20.75 5.22715 16.2728 0.75 10.75 0.75C5.22715 0.75 0.75 5.22715 0.75 10.75C0.75 16.2728 5.22715 20.75 10.75 20.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Register Asset
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 mt-6">
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                    <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium">Total Locations</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['total_locations'] }}</p>
                    <p class="text-emerald-200 text-xs mt-1">Active locations</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                    <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium">Total Assets</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['total_assets'] }}</p>
                    <p class="text-emerald-200 text-xs mt-1">Registered items</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                    <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium">In Use</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['assets_in_use'] }}</p>
                    <p class="text-emerald-200 text-xs mt-1">{{ $stats['total_assets'] > 0 ? round(($stats['assets_in_use'] / $stats['total_assets']) * 100) : 0 }}% utilization</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                    <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium">Available</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['assets_available'] }}</p>
                    <p class="text-emerald-200 text-xs mt-1">Ready for assignment</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Analytics Stats Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-11 h-11 flex items-center justify-center rounded-xl bg-brand-50 text-brand">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="text-xs font-semibold text-brand bg-brand-50 dark:bg-brand-50/10 dark:text-brand-light px-2.5 py-1 rounded-full">{{ $stats['total_locations'] }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider">Locations</p>
            <h3 class="text-xl lg:text-2xl font-bold text-gray-900 dark:text-white mt-0.5">{{ $stats['total_locations'] }}</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Manage asset locations</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-11 h-11 flex items-center justify-center rounded-xl bg-brand-50 text-brand">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
                <span class="text-xs font-semibold text-brand bg-brand-50 dark:bg-brand-50/10 dark:text-brand-light px-2.5 py-1 rounded-full">{{ $stats['total_categories'] }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider">Categories</p>
            <h3 class="text-xl lg:text-2xl font-bold text-gray-900 dark:text-white mt-0.5">{{ $stats['total_categories'] }}</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Asset classifications</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-11 h-11 flex items-center justify-center rounded-xl bg-brand-50 text-brand">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                </div>
                <span class="text-xs font-semibold text-brand bg-brand-50 dark:bg-brand-50/10 dark:text-brand-light px-2.5 py-1 rounded-full">{{ $stats['total_staff'] }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider">Staff</p>
            <h3 class="text-xl lg:text-2xl font-bold text-gray-900 dark:text-white mt-0.5">{{ $stats['total_staff'] }}</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Registered personnel</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-11 h-11 flex items-center justify-center rounded-xl bg-amber-50 text-amber-500 dark:bg-amber-900/30 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <span class="text-xs font-semibold text-amber-600 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-400 px-2.5 py-1 rounded-full">{{ $stats['pending_returns'] }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider">Pending Returns</p>
            <h3 class="text-xl lg:text-2xl font-bold text-gray-900 dark:text-white mt-0.5">{{ $stats['pending_returns'] }}</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Awaiting processing</p>
        </div>
    </div>



    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Recent Assets Table --}}
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">Recently Registered</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Latest assets added to the system</p>
                </div>
                <a href="{{ route('assets.index') }}" class="text-brand text-xs font-semibold hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-gray-800/50 text-gray-400 dark:text-gray-500 text-xs uppercase tracking-wider font-semibold">
                            <th class="px-5 sm:px-6 py-3.5">Code</th>
                            <th class="px-5 sm:px-6 py-3.5">Name</th>
                            <th class="hidden sm:table-cell px-5 sm:px-6 py-3.5">Category</th>
                            <th class="px-5 sm:px-6 py-3.5">Status</th>
                            <th class="hidden md:table-cell px-5 sm:px-6 py-3.5">Added</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($stats['recent_assets'] as $asset)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 sm:px-6 py-3.5">
                                <span class="font-mono text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">{{ $asset->asset_code }}</span>
                            </td>
                            <td class="px-5 sm:px-6 py-3.5">
                                <p class="font-semibold text-gray-700 dark:text-gray-300 text-sm">{{ $asset->name }}</p>
                            </td>
                            <td class="hidden sm:table-cell px-5 sm:px-6 py-3.5">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $asset->category->name ?? 'N/A' }}</span>
                            </td>
                            <td class="px-5 sm:px-6 py-3.5">
                                @php
                                    $statusColors = ['active' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300', 'maintenance' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300', 'retired' => 'bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400', 'lost' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300', 'damaged' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300'];
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full {{ $statusColors[$asset->status] ?? 'bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $asset->status === 'active' ? 'bg-emerald-500' : ($asset->status === 'maintenance' ? 'bg-amber-500' : 'bg-gray-400') }}"></span>
                                    {{ $asset->status }}
                                </span>
                            </td>
                            <td class="hidden md:table-cell px-5 sm:px-6 py-3.5 text-xs text-gray-400 dark:text-gray-500">{{ $asset->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <p class="text-gray-400 dark:text-gray-500 text-sm">No assets registered yet.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Quick Actions & Info --}}
        <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 sm:p-6">
                <h3 class="font-bold text-gray-900 dark:text-white text-sm mb-4">Quick Actions</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('assets.create') }}" class="flex flex-col items-center gap-2 p-3 bg-brand-50 dark:bg-brand-50/10 rounded-xl hover:bg-brand-100 dark:hover:bg-brand-50/20 transition-colors group">
                        <div class="w-9 h-9 flex items-center justify-center rounded-lg bg-brand text-white group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </div>
                        <span class="text-xs font-semibold text-brand">New Asset</span>
                    </a>
                    <a href="{{ route('asset-assignments.index') }}" class="flex flex-col items-center gap-2 p-3 bg-brand-50 dark:bg-brand-50/10 rounded-xl hover:bg-brand-100 dark:hover:bg-brand-50/20 transition-colors group">
                        <div class="w-9 h-9 flex items-center justify-center rounded-lg bg-brand text-white group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <span class="text-xs font-semibold text-brand">Assign Asset</span>
                    </a>
                    <a href="{{ route('asset-verifications.index') }}" class="flex flex-col items-center gap-2 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-xl hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors group">
                        <div class="w-9 h-9 flex items-center justify-center rounded-lg bg-orange-500 text-white group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-xs font-semibold text-orange-600 dark:text-orange-400">Verify Asset</span>
                    </a>
                    <a href="{{ route('reports.index') }}" class="flex flex-col items-center gap-2 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                        <div class="w-9 h-9 flex items-center justify-center rounded-lg bg-gray-500 text-white group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Reports</span>
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900 dark:text-white text-sm">Recent Activity</h3>
                    <a href="{{ route('activity-logs.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
                </div>
                <div class="space-y-3">
                    @forelse($stats['recent_activity'] as $log)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-2 h-2 rounded-full bg-brand flex-shrink-0"></span>
                        <div class="min-w-0">
                            <p class="text-gray-700 dark:text-gray-300 truncate">{{ $log->description }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $log->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">No recent activity.</p>
                    @endforelse
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            @php $unread = $stats['unread_notifications'] ?? 0; @endphp
                            {{ $unread }} unread notification{{ $unread != 1 ? 's' : '' }}
                        </span>
                        <a href="{{ route('notifications.index') }}" class="text-xs text-brand font-semibold hover:underline">View Notifications</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
