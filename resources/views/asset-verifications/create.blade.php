@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Record Audit Result</h1>

    <form action="{{ route('asset-verifications.store') }}" method="POST" class="space-y-6">
        @csrf
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Asset</label>
                <select name="asset_id" class="w-full rounded-xl border-slate-200" required>
                    @foreach($assets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->name }} (SN: {{ $asset->serial_number }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Found at Location</label>
                <select name="location_id" class="w-full rounded-xl border-slate-200" required>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Verified By</label>
                    <input type="text" name="verified_by" value="{{ auth()->user()->name }}" class="w-full rounded-xl border-slate-200 bg-slate-50" readonly>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Date</label>
                    <input type="date" name="verified_at" value="{{ date('Y-m-d') }}" class="w-full rounded-xl border-slate-200" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Condition</label>
                <div class="flex gap-3">
                    @foreach(['good', 'fair', 'broken', 'lost'] as $status)
                        <label class="flex-1 text-center border p-3 rounded-xl cursor-pointer hover:bg-slate-50 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                            <input type="radio" name="condition" value="{{ $status }}" class="hidden" required {{ $loop->first ? 'checked' : '' }}>
                            <span class="text-xs font-bold uppercase text-slate-600">{{ $status }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <input type="hidden" name="quantity_verified" value="1">

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Remarks (Repair Needs/Missing Info)</label>
                <textarea name="remark" rows="3" class="w-full rounded-xl border-slate-200" placeholder="Flag items needing repair or disposal here..."></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('asset-verifications.index') }}" class="px-6 py-2 text-slate-600 font-medium">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition">Save Audit Result</button>
        </div>
    </form>
</div>
@endsection
