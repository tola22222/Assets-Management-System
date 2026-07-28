@extends('layouts.app')
@section('title', 'Verification Details')
@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <a href="{{ route('asset-verifications.index') }}" class="text-sm text-brand font-bold hover:underline">&larr; Back</a>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Verification Details</h1>
        <div class="grid grid-cols-2 gap-6">
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Asset</p>
                <p class="font-bold text-slate-700">{{ $assetVerification->asset->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Location</p>
                <p class="font-bold text-slate-700">{{ $assetVerification->location->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Verified By</p>
                <p class="font-bold text-slate-700">{{ $assetVerification->verifiedBy->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Condition</p>
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $assetVerification->condition === 'good' ? 'bg-emerald-100 text-emerald-700' : ($assetVerification->condition === 'fair' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ ucfirst($assetVerification->condition) }}</span>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Quantity</p>
                <p class="font-bold text-slate-700">{{ $assetVerification->quantity_verified }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Date</p>
                <p class="font-bold text-slate-700">{{ $assetVerification->verified_at ? $assetVerification->verified_at->format('d M Y') : 'Pending' }}</p>
            </div>
            <div class="col-span-2">
                <p class="text-xs text-slate-400 uppercase font-bold">Remark</p>
                <p class="text-slate-700">{{ $assetVerification->remark ?? 'No remarks' }}</p>
            </div>
        </div>
        @if($assetVerification->image_path)
        <div class="mt-6">
            <p class="text-xs text-slate-400 uppercase font-bold mb-2">Evidence Photo</p>
            <img src="{{ asset('storage/'.$assetVerification->image_path) }}" class="max-w-sm rounded-xl border">
        </div>
        @endif
    </div>
</div>
@endsection
