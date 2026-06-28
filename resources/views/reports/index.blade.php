@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reports</h1>
        <p class="text-gray-500 dark:text-gray-400 text-sm">Generate and export reports.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @if(Auth::user()->isAdmin())
        <a href="{{ route('reports.inventory') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-brand-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <img src="{{ asset('images/Asset.svg') }}" class="w-6 h-6" alt="">
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Inventory Report</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View all assets with details</p>
        </a>
        <a href="{{ route('reports.assignments') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-emerald-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <img src="{{ asset('images/staff.svg') }}" class="w-6 h-6" alt="">
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Assignment Report</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Asset assignment history</p>
        </a>
        <a href="{{ route('reports.transfers') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-indigo-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <img src="{{ asset('images/report.svg') }}" class="w-6 h-6" alt="">
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Transfer Report</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Asset transfer history</p>
        </a>
        <a href="{{ route('reports.verifications') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-purple-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <img src="{{ asset('images/Dasboard.svg') }}" class="w-6 h-6" alt="">
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Verification Report</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Asset verification records</p>
        </a>
        <a href="{{ route('reports.returns') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-orange-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l6-6m0 0l6 6m-6-6v12a6 6 0 01-12 0v-3"/></svg>
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Return Report</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Asset return records</p>
        </a>
        <a href="{{ route('reports.disposed') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-red-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Disposed Assets</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Disposed asset records</p>
        </a>
        <a href="{{ route('reports.lost') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-rose-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Lost Assets</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Lost asset records</p>
        </a>
        <a href="{{ route('reports.locations') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-teal-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <img src="{{ asset('images/setting.svg') }}" class="w-6 h-6" alt="">
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">Location Report</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Location information</p>
        </a>
        <a href="{{ route('reports.qr-scans') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-gray-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <img src="{{ asset('images/qr-code.svg') }}" class="w-6 h-6" alt="">
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">QR Scan Report</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">QR scan history</p>
        </a>
        @else
        <a href="{{ route('reports.assignments') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-emerald-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">My Assignment History</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View your assignment records</p>
        </a>
        <a href="{{ route('reports.verifications') }}" class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group">
            <span class="p-3 bg-purple-50 rounded-xl inline-block group-hover:scale-110 transition flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <h3 class="font-bold text-gray-900 dark:text-white mt-4">My Verification History</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View your verification records</p>
        </a>
        @endif
    </div>
</div>
@endsection
