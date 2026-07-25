@extends('layouts.app')
@section('title', 'Search Asset Tracker')
@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-xl">{{ session('error') }}</div>
    @endif

    <div class="text-center">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Search Asset Tracker</h1>
        <p class="text-slate-500 dark:text-gray-400 mt-1">Scan an asset QR code to view details</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 p-8">
        <form action="{{ route('qr-scan.scan') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-2">Enter Asset Code</label>
                <input type="text" name="asset_code" placeholder="e.g., AST-2026-000001"
                    class="w-full px-6 py-4 text-lg font-mono border-2 border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none text-center"
                    autofocus>
            </div>
            <button type="submit" class="w-full bg-brand text-white py-4 rounded-xl text-lg font-bold hover:bg-brand-dark transition">
                <x-icon name="search-outline" class="w-6 h-6 inline mr-2"/>
                Look Up Asset
            </button>
        </form>

        <div class="mt-8 p-6 bg-slate-50 dark:bg-gray-700/50 rounded-xl border border-dashed border-slate-300 dark:border-gray-600">
            <p class="text-sm text-slate-500 dark:text-gray-400 text-center">
                Point your camera at a QR code or manually enter the asset code above.
            </p>
        </div>
    </div>
</div>
@endsection
