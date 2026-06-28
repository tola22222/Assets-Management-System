@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Notifications</h1>
            <p class="text-slate-500 dark:text-gray-400">Stay informed about system activities.</p>
        </div>
        <form action="{{ route('notifications.mark-all-read') }}" method="POST">
            @csrf
            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark">Mark All as Read</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        @forelse($notifications as $notification)
        <div class="p-6 border-b border-slate-100 dark:border-gray-700 flex items-start justify-between {{ $notification->is_read ? '' : 'bg-brand-50' }}">
            <div class="flex-1">
                <p class="font-bold text-slate-700 dark:text-gray-300">{{ $notification->message }}</p>
                <p class="text-xs text-slate-400 dark:text-gray-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if($notification->url)
                <a href="{{ $notification->url }}" class="text-brand text-sm font-bold hover:underline">View</a>
                @endif
                @if(!$notification->is_read)
                <form action="{{ route('notifications.mark-read', $notification) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs text-slate-400 dark:text-gray-500 hover:text-slate-600 dark:hover:text-gray-300">Mark Read</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="p-12 text-center">
            <div class="flex flex-col items-center gap-3">
                <svg class="w-12 h-12 text-slate-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                <p class="text-slate-400 dark:text-gray-500 font-medium">No notifications yet</p>
                <p class="text-xs text-slate-300 dark:text-gray-500">You'll see system alerts and updates here.</p>
            </div>
        </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
