@extends('layouts.app')
@section('title', 'Activity Logs')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Activity Logs</h1>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Audit trail of all system actions.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-5 sm:px-6 py-3.5 font-semibold tracking-wide">User</th>
                        <th class="px-5 sm:px-6 py-3.5 font-semibold tracking-wide">Action</th>
                        <th class="px-5 sm:px-6 py-3.5 font-semibold tracking-wide hidden md:table-cell">Description</th>
                        <th class="px-5 sm:px-6 py-3.5 font-semibold tracking-wide hidden md:table-cell">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @foreach($activityLogs as $log)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="px-5 sm:px-6 py-3.5 font-medium text-gray-900 dark:text-white">{{ $log->user->name ?? 'System' }}</td>
                        <td class="px-5 sm:px-6 py-3.5">
                            @php
                                $actionColors = [
                                    'Login' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                    'Logout' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                    'Create' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                    'Update' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                    'Delete' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                    'Assign' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
                                    'Verification' => 'bg-brand-100 text-brand dark:bg-brand-50/10 dark:text-brand-light',
                                    'Approve' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                ];
                            @endphp
                            <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $actionColors[$log->action] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="px-5 sm:px-6 py-3.5 text-sm text-gray-600 dark:text-gray-400 hidden md:table-cell">{{ $log->description }}</td>
                        <td class="px-5 sm:px-6 py-3.5 text-sm text-gray-400 dark:text-gray-500 hidden md:table-cell">{{ $log->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-5 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700">
            {{ $activityLogs->links() }}
        </div>
    </div>
</div>
@endsection
