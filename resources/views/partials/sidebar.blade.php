<aside class="w-72 bg-white border-r border-slate-200 hidden lg:flex flex-col h-screen sticky top-0">

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
    </div>

    <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">

        @php
            $base = 'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200';
            $active = 'bg-indigo-50 text-indigo-600 shadow-sm';
            $inactive = 'text-slate-600 hover:bg-slate-50 hover:text-slate-900';
        @endphp

        <a href="{{ route('dashboard') }}"
            class="{{ $base }} {{ request()->routeIs('dashboard') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
        </a>

        <a href="{{ route('assets.index') }}"
            class="{{ $base }} {{ request()->routeIs('assets.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
            </svg>
            Assets Registeration
        </a>

        <a href="{{ route('asset-assignments.index') }}"
            class="{{ $base }} {{ request()->routeIs('asset-assignments.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            Asset Assignments
        </a>

        <div class="pt-4 pb-2 px-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Setup</span>
        </div>

        <a href="{{ route('assets-locations.index') }}"
            class="{{ $base }} {{ request()->routeIs('assets-locations.*') ? $active : $inactive }}">
            Assets Locations
        </a>
        
        <a href="{{ route('categories.index') }}"
            class="{{ $base }} {{ request()->routeIs('categories.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            Asset Categories
        </a>

        <a href="{{ route('suppliers.index') }}"
            class="{{ $base }} {{ request()->routeIs('suppliers.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            Suppliers
        </a>

          <a href="{{ route('asset-stocks.index') }}"
            class="{{ $base }} {{ request()->routeIs('asset-stocks.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            Asset stocks
        </a>
         <a href="{{ route('staff.index') }}"
            class="{{ $base }} {{ request()->routeIs('staff.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
           staff
        </a>

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
                Logout
            </button>
        </form>
    </div>

</aside>
