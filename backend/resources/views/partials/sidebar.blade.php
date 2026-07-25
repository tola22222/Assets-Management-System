@php
    $user = Auth::user();
    $isAdmin = $user && $user->isOperationsHrManager();
    $groups = [
        'inventory' => ['assets.*', 'asset-assignments.*', 'asset-stocks.*', 'asset-verifications.*', 'asset-transfers.*', 'asset-returns.*', 'asset-disposals.*'],
        'people'    => ['staff.*', 'programs.*'],
        'settings'  => ['categories.*', 'assets-locations.*', 'suppliers.*'],
        'setting'   => ['users.*', 'settings.*'],
    ];

    $openGroup = '';
    foreach ($groups as $group => $routes) {
        if (request()->routeIs($routes)) { $openGroup = $group; break; }
    }
    $navClass = 'flex items-center gap-4 w-full px-4 py-3.5 rounded-xl font-medium transition';
    $activeClass = 'bg-brand-50 text-brand font-semibold';
    $inactiveClass = 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium';
    $dropdownActiveClass = 'bg-brand-50 text-brand font-semibold';
    $subClass = 'flex items-center gap-4 w-full pl-10 pr-4 py-3.5 rounded-xl text-sm font-medium transition';
    $subActive = 'text-brand font-semibold bg-brand-50 dark:bg-brand-50/10';
    $subInactive = 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800';
@endphp

<aside class="w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 flex flex-col justify-between h-screen sticky top-0 overflow-hidden"
    x-data="{ openGroup: '{{ $openGroup }}' }">

    <div class="flex-1 min-h-0 overflow-y-auto">
        <div class="flex items-center gap-3 px-4 py-6">
            <img src="{{ isset($appSettings['logo']) ? Storage::url($appSettings['logo']) : asset('images/logo.png') }}" alt="Logo" class="h-9 w-auto flex-shrink-0"/>
            <div>
                <p class="text-brand font-bold text-base leading-tight">{{ $appSettings['system_name'] ?? 'PEPY Asset' }}</p>
                <p class="text-gray-400 dark:text-gray-500 text-xs">{{ __('messages.app_subtitle') }}</p>
            </div>
        </div>

        <nav class="px-3 space-y-0.5">
            <a href="{{ route('dashboard') }}"
                class="{{ $navClass }} {{ request()->routeIs('dashboard') ? $activeClass : $inactiveClass }}">
                <img src="{{ asset('images/Dasboard.svg') }}" class="w-5 h-5 flex-shrink-0" alt="">
                {{ __('messages.dashboard') }}
            </a>

            <a href="{{ route('qr-scan.index') }}"
                class="{{ $navClass }} {{ request()->routeIs('qr-scan.*') ? $activeClass : $inactiveClass }}">
                <img src="{{ asset('images/qr-code.svg') }}" class="w-5 h-5 flex-shrink-0" alt="">
                {{ __('messages.qr_scanner') }}
            </a>

            @if($isAdmin)
            <div>
                <button @click="openGroup = openGroup === 'inventory' ? '' : 'inventory'"
                    class="flex items-center justify-between w-full px-4 py-3.5 rounded-xl font-medium transition {{ $openGroup === 'inventory' ? $dropdownActiveClass : $inactiveClass }}">
                    <div class="flex items-center gap-4 min-w-0">
                        <img src="{{ asset('images/Asset.svg') }}" class="w-5 h-5 flex-shrink-0" alt="">
                        <span class="truncate">{{ __('messages.asset_management') }}</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200 flex-shrink-0" :class="openGroup === 'inventory' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openGroup === 'inventory'" x-cloak x-collapse class="space-y-0">
                    <a href="{{ route('assets.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('assets.*') ? $subActive : $subInactive }}">{{ __('messages.asset_register') }}</a>
                    <a href="{{ route('asset-stocks.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('asset-stocks.*') ? $subActive : $subInactive }}">{{ __('messages.receive_assets') }}</a>
                    <a href="{{ route('asset-assignments.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('asset-assignments.*') ? $subActive : $subInactive }}">{{ __('messages.assignments') }}</a>
                    <a href="{{ route('asset-transfers.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('asset-transfers.*') ? $subActive : $subInactive }}">{{ __('messages.transfers') }}</a>
                    <a href="{{ route('asset-returns.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('asset-returns.*') ? $subActive : $subInactive }}">{{ __('messages.returns') }}</a>
                    <a href="{{ route('asset-verifications.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('asset-verifications.*') ? $subActive : $subInactive }}">{{ __('messages.verification') }}</a>
                    <a href="{{ route('asset-disposals.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('asset-disposals.*') ? $subActive : $subInactive }}">{{ __('messages.disposals') }}</a>
                </div>
            </div>
            @else
            <div class="pt-2">
                <p class="px-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('messages.my_assets') }}</p>
                <a href="{{ route('asset-assignments.index') }}"
                    class="{{ $subClass }} {{ request()->routeIs('asset-assignments.*') ? $subActive : $subInactive }}">{{ __('messages.my_assets') }}</a>
                <a href="{{ route('asset-returns.index') }}"
                    class="{{ $subClass }} {{ request()->routeIs('asset-returns.*') ? $subActive : $subInactive }}">{{ __('messages.return_requests') }}</a>
                <a href="{{ route('asset-transfers.index') }}"
                    class="{{ $subClass }} {{ request()->routeIs('asset-transfers.*') ? $subActive : $subInactive }}">{{ __('messages.transfer_requests') }}</a>
                <a href="{{ route('asset-verifications.index') }}"
                    class="{{ $subClass }} {{ request()->routeIs('asset-verifications.*') ? $subActive : $subInactive }}">{{ __('messages.verification') }}</a>
            </div>
            @endif

            <div>
                <button @click="openGroup = openGroup === 'people' ? '' : 'people'"
                    class="flex items-center justify-between w-full px-4 py-3.5 rounded-xl font-medium transition {{ $openGroup === 'people' ? $dropdownActiveClass : $inactiveClass }}">
                    <div class="flex items-center gap-4 min-w-0">
                        <img src="{{ asset('images/staff.svg') }}" class="w-5 h-5 flex-shrink-0" alt="">
                        <span class="truncate">{{ __('messages.people_programs') }}</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200 flex-shrink-0" :class="openGroup === 'people' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openGroup === 'people'" x-cloak x-collapse class="space-y-0">
                    <a href="{{ route('staff.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('staff.*') ? $subActive : $subInactive }}">{{ __('messages.staff_directory') }}</a>
                    <a href="{{ route('programs.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('programs.*') ? $subActive : $subInactive }}">{{ __('messages.programs') }}</a>
                </div>
            </div>

            <div>
                <button @click="openGroup = openGroup === 'settings' ? '' : 'settings'"
                    class="flex items-center justify-between w-full px-4 py-3.5 rounded-xl font-medium transition {{ $openGroup === 'settings' ? $dropdownActiveClass : $inactiveClass }}">
                    <div class="flex items-center gap-4 min-w-0">
                        <img src="{{ asset('images/system-setup.svg') }}" class="w-5 h-5 flex-shrink-0" alt="">
                        <span class="truncate">{{ __('messages.system_setup') }}</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200 flex-shrink-0" :class="openGroup === 'settings' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openGroup === 'settings'" x-cloak x-collapse class="space-y-0">
                    <a href="{{ route('categories.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('categories.*') ? $subActive : $subInactive }}">{{ __('messages.categories') }}</a>
                    <a href="{{ route('assets-locations.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('assets-locations.*') ? $subActive : $subInactive }}">{{ __('messages.locations') }}</a>
                    <a href="{{ route('suppliers.index') }}"
                        class="{{ $subClass }} {{ request()->routeIs('suppliers.*') ? $subActive : $subInactive }}">{{ __('messages.suppliers') }}</a>
                </div>
            </div>

            <a href="{{ route('reports.index') }}"
                class="{{ $navClass }} {{ request()->routeIs('reports.*') ? $activeClass : $inactiveClass }}">
                <img src="{{ asset('images/report.svg') }}" class="w-5 h-5 flex-shrink-0" alt="">
                {{ __('messages.reports') }}
            </a>
        </nav>
    </div>

    @if($isAdmin)
    <div class="space-y-0.5 px-4 pb-0">
        <div>
            <button @click="openGroup = openGroup === 'setting' ? '' : 'setting'"
                class="flex items-center justify-between w-full px-4 py-3.5 rounded-xl font-medium transition {{ $openGroup === 'setting' ? $dropdownActiveClass : $inactiveClass }}">
                <div class="flex items-center gap-4 min-w-0">
                    <img src="{{ asset('images/setting.svg') }}" class="w-5 h-5 flex-shrink-0" alt="">
                    <span class="truncate">{{ __('messages.setting') }}</span>
                </div>
                <svg class="w-4 h-4 transition-transform duration-200 flex-shrink-0" :class="openGroup === 'setting' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="openGroup === 'setting'" x-cloak x-collapse class="space-y-0">
                <a href="{{ route('users.index') }}"
                    class="{{ $subClass }} {{ request()->routeIs('users.*') ? $subActive : $subInactive }}">{{ __('messages.user_management') }}</a>
                <a href="{{ route('settings.index') }}"
                    class="{{ $subClass }} {{ request()->routeIs('settings.*') ? $subActive : $subInactive }}">{{ __('messages.system_settings') }}</a>
                <a href="{{ route('activity-logs.index') }}"
                    class="{{ $subClass }} {{ request()->routeIs('activity-logs.*') ? $subActive : $subInactive }}">{{ __('messages.activity_logs') }}</a>
            </div>
        </div>
    </div>
    @endif
    <div class="px-4 pb-4 -mt-1.5 space-y-1">

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit"
                class="flex items-center gap-4 w-full px-4 py-3.5 rounded-xl font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-950/50 transition">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                {{ __('messages.log_out') }}
            </button>
        </form>
    </div>
</aside>
