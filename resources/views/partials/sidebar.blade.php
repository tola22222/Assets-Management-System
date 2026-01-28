<aside class="w-72 bg-white border-r border-slate-200 hidden lg:flex flex-col h-screen sticky top-0">

    <!-- Logo -->
    <div class="px-6 py-6 border-b border-slate-100">
        <div class="flex items-center gap-3 text-indigo-600 font-bold text-xl tracking-tight">
            <div class="bg-indigo-600 p-2 rounded-lg shadow-sm">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            PEPY Asset Management
        </div>
    </div>

    <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">

        @php
            $base = 'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200';
            $active = 'bg-indigo-50 text-indigo-600 shadow-sm';
            $inactive = 'text-slate-600 hover:bg-slate-50 hover:text-slate-900';
        @endphp

        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}" class="{{ $base }} {{ request()->routeIs('dashboard') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            Dashboard
        </a>

        <!-- Assets -->
        <a href="{{ route('assets.index') }}" class="{{ $base }} {{ request()->routeIs('assets.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745"/>
            </svg>
            Assets
        </a>

        <!-- Stock -->
        {{-- <a href="{{ route('inventories.index') }}" class="{{ $base }} {{ request()->routeIs('inventories.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7"/>
            </svg>
            Stock Balance
        </a> --}}

        {{-- <a href="{{ route('stock-transfers.index') }}" class="{{ $base }} {{ request()->routeIs('stock-transfers.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4"/>
            </svg>
            Stock Transfers
        </a> --}}

        <!-- Assignments -->
        {{-- <a href="{{ route('assignments.index') }}" class="{{ $base }} {{ request()->routeIs('assignments.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M9 8h6"/>
            </svg>
            Assignments
        </a> --}}

        <!-- Verification -->
        {{-- <a href="{{ route('verifications.index') }}" class="{{ $base }} {{ request()->routeIs('verifications.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            Verification & Audit
        </a> --}}

        <!-- Reference Data -->
        <a href="{{ route('partner-schools.index') }}" class="{{ $base }} {{ request()->routeIs('schools.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14"/>
            </svg>
            Partner Schools
        </a>

        <a href="{{ route('asset-categories.index') }}" class="{{ $base }} {{ request()->routeIs('categories.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16"/>
            </svg>
            Asset Categories
        </a>

        {{-- <a href="{{ route('suppliers.index') }}" class="{{ $base }} {{ request()->routeIs('suppliers.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857"/>
            </svg>
            Suppliers
        </a> --}}

        <!-- Reports -->
        {{-- <a href="{{ route('reports.index') }}" class="{{ $base }} {{ request()->routeIs('reports.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-4M13 17V7M17 17v-2"/>
            </svg>
            Reports
        </a> --}}

        <!-- Logs -->
        {{-- <a href="{{ route('activity-logs.index') }}" class="{{ $base }} {{ request()->routeIs('activity-logs.*') ? $active : $inactive }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"/>
            </svg>
            Activity Logs
        </a> --}}

    </nav>

    <!-- Logout -->
    <div class="p-4 border-t border-slate-100">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-500 hover:bg-red-50 rounded-xl transition">
                Logout
            </button>
        </form>
    </div>

</aside>
