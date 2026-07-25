@props(['title', 'subtitle', 'buttonText' => null, 'buttonAction' => null, 'search' => false, 'searchPlaceholder' => 'Search...'])

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-bold text-gray-800 tracking-tight">{{ $title }}</h1>
        <p class="text-gray-500 text-sm mt-0.5">{{ $subtitle }}</p>
    </div>
    <div class="flex items-center gap-3">
        @if($search)
        <div class="relative w-72">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </span>
            <input type="text" placeholder="{{ $searchPlaceholder }}"
                class="w-full bg-white border border-gray-200 rounded-xl py-2.5 pl-11 pr-4 text-sm tracking-wide placeholder-gray-400 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition">
        </div>
        @endif
        @if($buttonText && $buttonAction)
            <button onclick="{{ $buttonAction }}"
                class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm flex items-center gap-2 transition">
<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.75 6.75V14.75M14.75 10.75H6.75M10.75 20.75C16.2728 20.75 20.75 16.2728 20.75 10.75C20.75 5.22715 16.2728 0.75 10.75 0.75C5.22715 0.75 0.75 5.22715 0.75 10.75C0.75 16.2728 5.22715 20.75 10.75 20.75Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                {{ $buttonText }}
            </button>
        @endif
    </div>
</div>
