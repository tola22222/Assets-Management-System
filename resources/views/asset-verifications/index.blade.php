@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Verification & Audit</h1>
            <p class="text-slate-500 text-sm">Bi-annual condition tracking history.</p>
        </div>
        <a href="{{ route('asset-verifications.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl flex items-center gap-2 transition font-semibold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Perform New Audit
        </a>
    </div>

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