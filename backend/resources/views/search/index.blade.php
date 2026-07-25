@extends('layouts.app')
@section('title', 'Search Results')
@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Search Results</h1>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Showing results for "{{ $q }}"</p>
    </div>

    @if($results['assets']->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <h3 class="font-bold text-gray-900 dark:text-white">Assets ({{ $results['assets']->count() }})</h3>
            </div>
            <a href="{{ route('assets.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-5 sm:px-6 py-3.5">Code</th>
                        <th class="px-5 sm:px-6 py-3.5">Name</th>
                        <th class="hidden sm:table-cell px-5 sm:px-6 py-3.5">Category</th>
                        <th class="px-5 sm:px-6 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($results['assets'] as $asset)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="px-5 sm:px-6 py-3.5">
                            <span class="font-mono text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">{{ $asset->asset_code }}</span>
                        </td>
                        <td class="px-5 sm:px-6 py-3.5">
                            <a href="{{ route('assets.show', $asset) }}" class="font-semibold text-gray-700 dark:text-gray-300 hover:text-brand">{{ $asset->name }}</a>
                        </td>
                        <td class="hidden sm:table-cell px-5 sm:px-6 py-3.5 text-gray-500 dark:text-gray-400">{{ $asset->category->name ?? 'N/A' }}</td>
                        <td class="px-5 sm:px-6 py-3.5">
                            @php $c = ['active'=>'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300','maintenance'=>'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300','retired'=>'bg-gray-100 dark:bg-gray-900 text-gray-500','lost'=>'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300','damaged'=>'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300']; @endphp
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $c[$asset->status] ?? 'bg-gray-100 text-gray-500' }}">{{ $asset->status }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(isset($results['staff']) && $results['staff']->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                <h3 class="font-bold text-gray-900 dark:text-white">Staff ({{ $results['staff']->count() }})</h3>
            </div>
            <a href="{{ route('staff.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
        </div>
        @foreach($results['staff'] as $staff)
        <div class="px-5 sm:px-6 py-3.5 border-b border-gray-100 dark:border-gray-700 last:border-b-0 flex items-center gap-3 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
            <div class="w-8 h-8 rounded-full bg-brand text-white flex items-center justify-center text-xs font-bold">{{ substr($staff->full_name, 0, 1) }}</div>
            <div>
                <p class="font-semibold text-gray-700 dark:text-gray-300 text-sm">{{ $staff->full_name }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $staff->position }} · {{ $staff->email }}</p>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if($results['categories']->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                <h3 class="font-bold text-gray-900 dark:text-white">Categories ({{ $results['categories']->count() }})</h3>
            </div>
            <a href="{{ route('categories.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
        </div>
        <div class="px-5 sm:px-6 py-3.5 space-y-2">
            @foreach($results['categories'] as $cat)
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $cat->name }} <span class="text-xs text-gray-400 dark:text-gray-500 font-normal">({{ $cat->short_name }})</span></p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($results['suppliers']->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/></svg>
                <h3 class="font-bold text-gray-900 dark:text-white">Suppliers ({{ $results['suppliers']->count() }})</h3>
            </div>
            <a href="{{ route('suppliers.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
        </div>
        <div class="px-5 sm:px-6 py-3.5 space-y-2">
            @foreach($results['suppliers'] as $supplier)
            <div>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $supplier->name }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $supplier->phone }} · {{ $supplier->address }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($results['locations']->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                <h3 class="font-bold text-gray-900 dark:text-white">Locations ({{ $results['locations']->count() }})</h3>
            </div>
            <a href="{{ route('assets-locations.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
        </div>
        <div class="px-5 sm:px-6 py-3.5 space-y-2">
            @foreach($results['locations'] as $location)
            <div>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $location->name }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $location->type }} · {{ $location->description }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($results['programs']->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                <h3 class="font-bold text-gray-900 dark:text-white">Programs ({{ $results['programs']->count() }})</h3>
            </div>
            <a href="{{ route('programs.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
        </div>
        <div class="px-5 sm:px-6 py-3.5 space-y-2">
            @foreach($results['programs'] as $program)
            <div>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $program->name }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $program->description }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(isset($results['users']) && $results['users']->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                <h3 class="font-bold text-gray-900 dark:text-white">Users ({{ $results['users']->count() }})</h3>
            </div>
            <a href="{{ route('users.index') }}" class="text-xs text-brand font-semibold hover:underline">View All</a>
        </div>
        <div class="px-5 sm:px-6 py-3.5 space-y-2">
            @foreach($results['users'] as $user)
            <div>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $user->name }} <span class="text-xs text-gray-400 font-normal">({{ $user->role }})</span></p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $user->email }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @php
        $hasResults = collect($results)->filter(function($items) { return $items->isNotEmpty(); })->isNotEmpty();
    @endphp
    @unless($hasResults)
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 text-center">
        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <p class="text-gray-400 dark:text-gray-500 font-medium">No results found for "{{ $q }}"</p>
        <p class="text-xs text-gray-300 dark:text-gray-600 mt-1">Try a different search term.</p>
    </div>
    @endunless
</div>
@endsection
