@php $user = Auth::user(); @endphp
<header class="bg-white dark:bg-gray-900 h-16 lg:h-20 px-4 lg:px-8 flex items-center justify-between border-b border-gray-200 dark:border-gray-700 sticky top-0 z-30">
    <div class="flex items-center gap-3 lg:hidden">
        <button @click="sidebarOpen = !sidebarOpen" class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-brand rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <span class="font-bold text-sm text-brand">{{ $appSettings['system_name'] ?? 'AMS' }}</span>
    </div>

    <form action="{{ route('search') }}" method="GET" class="hidden sm:block relative w-full max-w-xs lg:max-w-sm">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </span>
        <input type="text" name="q" placeholder="{{ __('messages.search_placeholder') }}"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg py-2 pl-10 pr-4 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200 dark:placeholder-gray-500">
    </form>

    <div class="flex items-center gap-3">

        <a href="{{ route('qr-scan.index') }}" class="p-2 text-gray-400 hover:text-brand rounded-lg hover:bg-gray-100 lg:hidden transition" title="Scan QR">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
            </svg>
        </a>
        <a href="{{ route('notifications.index') }}" class="relative p-2 text-gray-400 hover:text-brand rounded-lg hover:bg-gray-100 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            @if($user && $user->unreadNotifications()->count() > 0)
                <span class="absolute top-1 right-1 w-3.5 h-3.5 bg-red-500 text-white text-[8px] font-bold rounded-full flex items-center justify-center ring-2 ring-white">
                    {{ $user->unreadNotifications()->count() > 9 ? '9+' : $user->unreadNotifications()->count() }}
                </span>
            @endif
        </a>
        <div class="h-5 w-px bg-gray-200 hidden sm:block"></div>
        <a href="{{ route('profile.show') }}" class="flex items-center gap-2 hover:opacity-80 transition pl-1">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 leading-tight">{{ $user->name ?? 'User' }}</p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 capitalize leading-tight">{{ $user->role ?? '' }}</p>
            </div>
            <img class="w-8 h-8 rounded-full object-cover flex-shrink-0 border border-gray-200"
                src="{{ $user->photo_url ?? 'https://ui-avatars.com/api/?name=User&background=128a43&color=fff' }}" alt="Avatar">
        </a>
    </div>
</header>
