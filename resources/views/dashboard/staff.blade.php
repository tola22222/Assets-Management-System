@extends('layouts.app')
@section('title', 'Staff Dashboard')
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
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold">Welcome back, {{ Auth::user()->name ?? 'Staff' }}!</h1>
                    <p class="text-emerald-100 text-sm sm:text-base mt-1">Your assigned assets and requests overview.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mt-6">
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                    <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium">My Assets</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $myAssignments->count() }}</p>
                    <p class="text-emerald-200 text-xs mt-1">Currently assigned</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                    <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium">Pending Returns</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $pendingReturns }}</p>
                    <p class="text-emerald-200 text-xs mt-1">Awaiting processing</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                    <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium">Verifications</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $upcomingVerifications }}</p>
                    <p class="text-emerald-200 text-xs mt-1">Pending review</p>
                </div>
            </div>
        </div>
    </div>

    {{-- My Assigned Assets --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">My Assigned Assets</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-gray-800/50 text-gray-400 dark:text-gray-500 text-xs uppercase tracking-wider font-semibold">
                        <th class="px-5 sm:px-6 py-3.5">Asset</th>
                        <th class="px-5 sm:px-6 py-3.5">Location</th>
                        <th class="hidden sm:table-cell px-5 sm:px-6 py-3.5">Date</th>
                        <th class="px-5 sm:px-6 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($myAssignments as $assignment)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 sm:px-6 py-3.5">
                            <p class="font-semibold text-gray-700 dark:text-gray-300 text-sm">{{ $assignment->asset->name ?? 'N/A' }}</p>
                            <span class="font-mono text-[10px] text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-900 px-1.5 py-0.5 rounded">{{ $assignment->asset->asset_code ?? '' }}</span>
                        </td>
                        <td class="px-5 sm:px-6 py-3.5 text-xs sm:text-sm text-gray-600 dark:text-gray-400">{{ $assignment->location->name ?? 'N/A' }}</td>
                        <td class="hidden sm:table-cell px-5 sm:px-6 py-3.5 text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ $assignment->assigned_date?->format('d M Y') ?? 'N/A' }}</td>
                        <td class="px-5 sm:px-6 py-3.5">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full {{ $assignment->status === 'active' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300' : ($assignment->status === 'pending' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' : 'bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400') }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $assignment->status === 'active' ? 'bg-emerald-500' : ($assignment->status === 'pending' ? 'bg-amber-500' : 'bg-gray-400') }}"></span>
                                {{ strtoupper($assignment->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-5 sm:px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm">No assets assigned to you yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('qr-scan.index') }}" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all group flex items-center gap-4">
            <div class="w-11 h-11 flex items-center justify-center rounded-xl bg-brand-50 text-brand group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            </div>
            <div><p class="font-bold text-gray-900 dark:text-white text-sm">Scan QR</p><p class="text-xs text-gray-500 dark:text-gray-400">View asset details</p></div>
        </a>
        <a href="{{ route('asset-returns.index') }}" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all group flex items-center gap-4">
            <div class="w-11 h-11 flex items-center justify-center rounded-xl bg-amber-50 text-amber-500 dark:bg-amber-900/30 dark:text-amber-400 group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </div>
            <div><p class="font-bold text-gray-900 dark:text-white text-sm">Return</p><p class="text-xs text-gray-500 dark:text-gray-400">Request return</p></div>
        </a>
        <a href="{{ route('profile.show') }}" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all group flex items-center gap-4">
            <div class="w-11 h-11 flex items-center justify-center rounded-xl bg-brand-50 text-brand group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div><p class="font-bold text-gray-900 dark:text-white text-sm">Profile</p><p class="text-xs text-gray-500 dark:text-gray-400">Manage account</p></div>
        </a>
    </div>
</div>
@endsection
