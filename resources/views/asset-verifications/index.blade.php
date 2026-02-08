@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <x-page-header
        title="Asset Inventory Stock"
        subtitle="Bi-annual condition tracking history."
        buttonText="Perform New Audit"
        buttonAction="{{ route('asset-verifications.create') }}"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Date</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Asset</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Condition</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Location</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Verified By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($verifications as $audit)
                <tr class="hover:bg-slate-50/50">
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $audit->verified_at->format('d M Y') }}</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-slate-900">{{ $audit->asset->name }}</div>
                        <div class="text-xs text-slate-500">SN: {{ $audit->asset->serial_number }}</div>
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $color = match($audit->condition) {
                                'good' => 'bg-green-100 text-green-700',
                                'fair' => 'bg-blue-100 text-blue-700',
                                'broken' => 'bg-orange-100 text-orange-700',
                                'lost' => 'bg-red-100 text-red-700',
                            };
                        @endphp
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase {{ $color }}">
                            {{ $audit->condition }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $audit->location->name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 font-medium">{{ $audit->verified_by }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-100">
            {{ $verifications->links() }}
        </div>
    </div>
</div>
@endsection
