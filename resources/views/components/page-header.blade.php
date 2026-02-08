@props([
    'title',
    'subtitle' => null,
    'buttonText' => null,
    'buttonAction' => null,
    'buttonIcon' => 'plus' {{-- Default icon --}}
])

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white p-6 rounded-2xl shadow-sm border border-slate-200 gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-slate-500 text-sm font-medium">{{ $subtitle }}</p>
        @endif
    </div>

    @if($buttonText)
        <button
            @if(str_contains($buttonAction, '(')) onclick="{{ $buttonAction }}" @else href="{{ $buttonAction }}" @endif
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center gap-2 shrink-0">

            {{-- Dynamic Icon --}}
            @if($buttonIcon === 'plus')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round" />
                </svg>
            @elseif($buttonIcon === 'download')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            @endif

            {{ $buttonText }}
        </button>
    @endif
</div>
