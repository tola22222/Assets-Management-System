@php
    /**
     * Define Group Logic
     * This determines which dropdown is expanded on page load
     */
    $openGroup = '';

    if (request()->routeIs('assets.*', 'asset-assignments.*', 'asset-stocks.*', 'asset-verifications.*', 'reports.*')) {
        $openGroup = 'inventory';
    } elseif (request()->routeIs('staff.*', 'programs.*')) {
        $openGroup = 'people';
    } elseif (request()->routeIs('categories.*', 'assets-locations.*', 'suppliers.*', 'conditions.*')) {
        $openGroup = 'settings';
    }

    // Design Tokens
    $baseClass =
        'flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200';
    $subLinkClass =
        'flex items-center gap-3 pl-12 pr-4 py-2 rounded-xl text-sm font-medium transition-all duration-200';
    $activeTab = 'bg-indigo-50 text-indigo-600 shadow-sm';
    $inactiveTab = 'text-slate-600 hover:bg-slate-50 hover:text-slate-900';
@endphp

<aside class="w-72 bg-white border-r border-slate-200 hidden lg:flex flex-col h-screen sticky top-0"
    x-data="{ openGroup: '{{ $openGroup }}' }">
    <div class="px-6 py-6 border-b border-slate-100">
        <div class="flex items-center gap-3 text-indigo-600 font-bold text-xl tracking-tight">
            <div class="bg-indigo-600 p-2 rounded-lg shadow-sm">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            PEPY Asset
        </div>
        <div class="text-[10px] uppercase tracking-widest text-slate-400 font-bold mt-2 px-1">
            Asset Management System
        </div>
    </div>

    <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">

        <a href="{{ route('dashboard') }}"
            class="{{ $baseClass }} {{ request()->routeIs('dashboard') ? $activeTab : $inactiveTab }}">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2 7-7 7 7M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3" />
                </svg>
                Dashboard
            </div>
        </a>

        <div class="space-y-1">
            <button @click="openGroup = openGroup === 'inventory' ? '' : 'inventory'"
                class="{{ $baseClass }} {{ $openGroup === 'inventory' ? 'text-indigo-600 bg-slate-50/50' : $inactiveTab }}">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    Asset Management
                </div>
                <svg class="w-4 h-4 transition-transform duration-200"
                    :class="openGroup === 'inventory' ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="openGroup === 'inventory'" x-cloak class="space-y-1">
                <a href="{{ route('assets.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('assets.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Asset
                    Register</a>
                <a href="{{ route('asset-stocks.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('asset-stocks.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Stock
                    Movements</a>
                <a href="{{ route('asset-assignments.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('asset-assignments.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Assignments
                    & Handover</a>
                <a href="{{ route('asset-verifications.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('asset-verifications.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Asset
                    Verification</a>
            </div>
        </div>

        <div class="space-y-1">
            <button @click="openGroup = openGroup === 'people' ? '' : 'people'"
                class="{{ $baseClass }} {{ $openGroup === 'people' ? 'text-indigo-600 bg-slate-50/50' : $inactiveTab }}">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20H7m10 0v-2a5 5 0 00-10 0v2m5-13a3 3 0 110-6 3 3 0 010 6z" />
                    </svg>
                    People & Programs
                </div>
                <svg class="w-4 h-4 transition-transform duration-200"
                    :class="openGroup === 'people' ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="openGroup === 'people'" x-cloak class="space-y-1">
                <a href="{{ route('staff.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('staff.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Staff
                    Directory</a>
                <a href="{{ route('programs.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('programs.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Programs</a>
                
            </div>
        </div>

        <a href="{{ route('reports.index') }}"
            class="{{ $baseClass }} {{ request()->routeIs('reports.*') ? $activeTab : $inactiveTab }}">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-4M5 21h14" />
                </svg>
                Reports
            </div>
        </a>

        <div class="space-y-1">
            <button @click="openGroup = openGroup === 'settings' ? '' : 'settings'"
                class="{{ $baseClass }} {{ $openGroup === 'settings' ? 'text-indigo-600 bg-slate-50/50' : $inactiveTab }}">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                    System Setup
                </div>
                <svg class="w-4 h-4 transition-transform duration-200"
                    :class="openGroup === 'settings' ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="openGroup === 'settings'" x-cloak class="space-y-1">
                <a href="{{ route('categories.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('categories.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Asset
                    Categories</a>
                <a href="{{ route('assets-locations.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('assets-locations.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Locations</a>
                <a href="{{ route('suppliers.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('suppliers.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Suppliers</a>
                {{-- <a href="{{ route('.index') }}"
                    class="{{ $subLinkClass }} {{ request()->routeIs('conditions.*') ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}">Condition
                    Types</a> --}}
            </div>
        </div>
    </nav>

    <div class="p-4 border-t border-slate-100">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit"
                class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-500 hover:bg-red-50 rounded-xl transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Sign Out
            </button>
        </form>
    </div>
</aside>
