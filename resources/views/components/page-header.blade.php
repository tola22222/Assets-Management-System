@props([
    'title',
    'subtitle' => null,
    'buttonText' => null,
    'buttonAction' => null,
    'buttonIcon' => 'plus'
])

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-slate-500 text-sm font-medium">{{ $subtitle }}</p>
        @endif
    </div>

    @if($buttonText)
        <div class="flex items-center gap-3">
            <button
                @if(str_contains($buttonAction, '('))
                    onclick="{{ $buttonAction }}"
                @else
                    onclick="window.location.href='{{ $buttonAction }}'"
                @endif
                class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition tracking-wide flex items-center gap-2">

                @if($buttonIcon === 'plus')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                @elseif($buttonIcon === 'download')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                @endif

                {{ $buttonText }}
            </button>
        </div>
    @endif
</div>
