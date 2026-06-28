@extends('layouts.app')
@section('title', 'Assignment Details')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6">
    <a href="{{ route('asset-assignments.index') }}" class="text-sm text-brand font-bold hover:underline inline-block mb-6">&larr; Back</a>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Assignment Details</h1>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Asset</p>
                <p class="font-bold text-slate-700">{{ $assetAssignment->asset->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Asset Code</p>
                <p class="font-mono text-sm text-slate-600">{{ $assetAssignment->asset->asset_code ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Assigned To</p>
                <p class="font-bold text-slate-700">{{ $assetAssignment->recipient_name }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Type</p>
                <p class="capitalize text-slate-600">{{ $assetAssignment->assigned_to_type }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Location</p>
                <p class="font-bold text-slate-700">{{ $assetAssignment->location->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Quantity</p>
                <p class="font-bold text-slate-700">{{ $assetAssignment->quantity }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Assigned Date</p>
                <p class="font-bold text-slate-700">{{ $assetAssignment->assigned_date ? $assetAssignment->assigned_date->format('d M Y') : 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Due Date</p>
                <p class="font-bold text-slate-700">{{ $assetAssignment->due_date ? $assetAssignment->due_date->format('d M Y') : 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Status</p>
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $assetAssignment->status_badge_class }}">{{ strtoupper($assetAssignment->status) }}</span>
            </div>
        </div>
        @if(Auth::user()->isAdmin() && $assetAssignment->status !== 'returned')
        <div class="flex flex-col sm:flex-row gap-3 mt-8 pt-6 border-t border-slate-100">
            <a href="{{ route('asset-assignments.index') }}" class="px-6 py-2.5 border border-slate-300 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 text-center">Back</a>
        </div>
        @endif
    </div>
</div>
@endsection
