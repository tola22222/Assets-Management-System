@extends('layouts.app')
@section('title', 'Return Details')
@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <a href="{{ route('asset-returns.index') }}" class="text-sm text-brand font-bold hover:underline">&larr; Back to Returns</a>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Return Details</h1>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Asset</p>
                <p class="font-bold text-slate-700">{{ $return->asset->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Asset Code</p>
                <p class="font-mono text-slate-600">{{ $return->asset->asset_code ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Returned By</p>
                <p class="font-bold text-slate-700">{{ $return->returnedBy->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Return Date</p>
                <p class="font-bold text-slate-700">{{ $return->return_date ? $return->return_date->format('d M Y') : 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Condition</p>
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                    {{ $return->condition === 'good' ? 'bg-emerald-100 text-emerald-700' : ($return->condition === 'fair' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                    {{ ucfirst($return->condition) }}
                </span>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Status</p>
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                    {{ $return->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($return->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                    {{ strtoupper($return->status) }}
                </span>
            </div>
            <div class="col-span-2">
                <p class="text-xs text-slate-400 uppercase font-bold">Damage Notes</p>
                <p class="text-slate-700">{{ $return->damage_notes ?? 'None' }}</p>
            </div>
            @if($return->admin_notes)
            <div class="col-span-2">
                <p class="text-xs text-slate-400 uppercase font-bold">Admin Notes</p>
                <p class="text-slate-700">{{ $return->admin_notes }}</p>
            </div>
            @endif
        </div>

        @if($return->image_path)
        <div class="mt-6">
            <p class="text-xs text-slate-400 uppercase font-bold mb-2">Evidence Photo</p>
            <img src="{{ asset('storage/'.$return->image_path) }}" class="max-w-sm rounded-xl border border-slate-200">
        </div>
        @endif
    </div>
</div>
@endsection
