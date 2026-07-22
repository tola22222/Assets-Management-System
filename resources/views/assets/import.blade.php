@extends('layouts.app')
@section('title', 'Import Assets')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif
    @if(session('importErrors') && count(session('importErrors')))
    <div class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 px-4 py-3 rounded-xl text-sm">
        <p class="font-semibold mb-1">{{ count(session('importErrors')) }} row(s) were skipped:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach(session('importErrors') as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <x-page-header title="Import Assets" subtitle="Bulk-register assets from a spreadsheet (CSV — open and save from Excel)" />

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 max-w-2xl space-y-6">
        <div class="flex items-start gap-3 bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
            <svg class="w-5 h-5 text-brand flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <p class="font-semibold text-gray-800 dark:text-gray-200">How it works</p>
                <ol class="list-decimal list-inside mt-1 space-y-1">
                    <li>Download the template below and fill it in Excel (or any spreadsheet app).</li>
                    <li>Save/export it as CSV.</li>
                    <li><strong>name</strong>, <strong>category</strong>, and <strong>location</strong> are all required — category and location must each match an existing name exactly.</li>
                    <li>Upload the file. Asset codes and QR codes are generated automatically for each row.</li>
                </ol>
            </div>
        </div>

        <a href="{{ route('assets.import.template') }}"
            class="inline-flex items-center gap-2 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-semibold text-sm px-5 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            Download CSV Template
        </a>

        <form action="{{ route('assets.import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 pt-2 border-t border-gray-100 dark:border-gray-700">
            @csrf
            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">CSV File <span class="text-red-500">*</span></label>
                <input type="file" name="file" accept=".csv,.txt" required
                    class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand">
            </div>
            <button type="submit"
                class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">Upload &amp; Import</button>
        </form>
    </div>
</div>
@endsection
