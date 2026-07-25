@extends('layouts.app')
@section('title', $location->name)
@section('content')
<div class="space-y-8">
    <a href="{{ route('assets-locations.index') }}" class="text-sm text-brand font-bold hover:underline">&larr; Back to Locations</a>

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $location->name }}</h1>
            <p class="text-slate-500 dark:text-gray-400">{{ ucfirst($location->type) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800">Assets at this Location</h3>
        </div>
        <table class="w-full text-left">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-xs font-bold text-slate-500 uppercase">Asset Code</th>
                    <th class="px-6 py-3 text-xs font-bold text-slate-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-xs font-bold text-slate-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-xs font-bold text-slate-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($assets as $asset)
                <tr>
                    <td class="px-6 py-3 font-mono text-sm text-slate-500">{{ $asset->asset_code }}</td>
                    <td class="px-6 py-3 font-bold text-slate-700">{{ $asset->name }}</td>
                    <td class="px-6 py-3 text-sm text-slate-600">{{ $asset->category->name ?? 'N/A' }}</td>
                    <td class="px-6 py-3 text-sm font-bold text-slate-700">{{ ucfirst($asset->status) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400">No assets at this location.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
