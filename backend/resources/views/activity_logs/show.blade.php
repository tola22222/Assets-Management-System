@extends('layouts.app')
@section('title', 'Activity Details')
@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6">
    <a href="{{ route('activity-logs.index') }}" class="text-sm text-brand font-bold hover:underline inline-block mb-6">&larr; Back</a>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-white mb-6">Activity Details</h1>
        <div class="space-y-4">
            <div>
                <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Action</p>
                <p class="font-bold text-slate-700 dark:text-gray-300">{{ $activityLog->action }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">User</p>
                <p class="font-bold text-slate-700 dark:text-gray-300">{{ $activityLog->user->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Description</p>
                <p class="text-slate-600 dark:text-gray-400">{{ $activityLog->description }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Date & Time</p>
                <p class="font-bold text-slate-700 dark:text-gray-300">{{ $activityLog->created_at->format('d M Y H:i:s') }}</p>
            </div>
        </div>
        <div class="mt-8">
            <a href="{{ route('activity-logs.index') }}" class="inline-block px-6 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl text-sm font-bold text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-800">Back to Logs</a>
        </div>
    </div>
</div>
@endsection
