@extends('layouts.app')

@section('content')
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100">
        <h2 class="text-xl font-bold text-slate-800">System Logs</h2>
    </div>

    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">User</th>
                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Action</th>
                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Description</th>
                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($activityLogs as $log)
            <tr class="hover:bg-slate-50/50">
                <td class="px-6 py-4 font-medium text-slate-700">{{ $log->user->name ?? 'System' }}</td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 rounded text-xs font-bold uppercase {{ $log->action == 'Login' ? 'bg-green-100 text-green-700' : 'bg-indigo-100 text-indigo-700' }}">
                        {{ $log->action }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">{{ $log->description }}</td>
                <td class="px-6 py-4 text-sm text-slate-400">{{ $log->created_at->diffForHumans() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="p-4">
        {{ $activityLogs->links() }}
    </div>
</div>
@endsection