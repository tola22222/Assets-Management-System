<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $asset->name }} - {{ $asset->asset_code }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Kantumruy Pro', 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-start sm:items-center justify-center p-0 sm:p-4">

    <div class="w-full max-w-lg mx-auto sm:my-8">
        <div class="bg-white shadow-sm sm:shadow-lg sm:rounded-2xl border-0 sm:border sm:border-gray-200 overflow-hidden">

            {{-- ===== HEADER ===== --}}
            <div class="relative px-5 pt-8 pb-20 sm:px-8 sm:pt-10 sm:pb-24" style="background: linear-gradient(135deg, #128a43 0%, #0f7a3a 50%, #0b6a30 100%);">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-white/50 text-[10px] sm:text-xs font-bold uppercase tracking-[0.2em]">Asset Information</p>
                        <h1 class="text-xl sm:text-2xl font-black text-white mt-1.5 leading-tight break-words">{{ $asset->name }}</h1>
                        <p class="text-white/60 font-mono text-xs sm:text-sm mt-1">{{ $asset->asset_code }}</p>
                    </div>
                    <span class="shrink-0 px-3 py-1.5 rounded-lg text-[11px] sm:text-xs font-bold tracking-wide {{ $asset->status === 'active' ? 'bg-white/15 text-white' : 'bg-white/10 text-white/60' }}">
                        {{ strtoupper($asset->status) }}
                    </span>
                </div>

                {{-- Floating QR Card --}}
                @if($asset->qr_code_url)
                <div class="absolute -bottom-10 left-5 sm:left-8 flex items-end gap-3 z-10">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-2">
                        <img src="{{ $asset->qr_code_url }}" alt="QR" class="w-16 h-16 sm:w-20 sm:h-20">
                    </div>
                    <div class="flex gap-1.5 pb-1 no-print">
                        <button onclick="window.print()"
                            class="px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white text-[11px] font-semibold rounded-lg hover:bg-white/30 transition border border-white/10">
                            Print
                        </button>
                        <a href="{{ route('assets.download-qr', $asset->id) }}"
                            class="px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white text-[11px] font-semibold rounded-lg hover:bg-white/30 transition border border-white/10">
                            PNG
                        </a>
                    </div>
                </div>
                @endif
            </div>

            {{-- ===== BODY ===== --}}
            <div class="px-5 sm:px-8 pt-14 pb-6 space-y-6">

                {{-- Asset Image --}}
                @if($asset->image_url)
                <div class="-mx-5 sm:-mx-8">
                    <img src="{{ $asset->image_url }}" alt="{{ $asset->name }}"
                        class="w-full h-48 sm:h-56 object-cover">
                </div>
                @endif

                {{-- Detail Grid --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-gray-50 rounded-xl px-3.5 py-3">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Category</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate">{{ $asset->category->name ?? 'N/A' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl px-3.5 py-3">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Brand</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate">{{ $asset->brand ?? 'N/A' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl px-3.5 py-3">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Model</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate">{{ $asset->model ?? 'N/A' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl px-3.5 py-3">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Serial No.</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate font-mono">{{ $asset->serial_number ?? 'N/A' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl px-3.5 py-3">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Condition</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ ucfirst($asset->condition) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl px-3.5 py-3">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Price</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $asset->purchase_price ? '$'.number_format($asset->purchase_price, 2) : 'N/A' }}</p>
                    </div>
                </div>

                {{-- Extra Fields --}}
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Purchase Date</p>
                        <p class="font-semibold text-gray-700 mt-0.5">{{ $asset->purchase_date ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Status</p>
                        <span class="inline-block mt-0.5 px-2.5 py-0.5 rounded text-xs font-bold {{ $asset->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ strtoupper($asset->status) }}
                        </span>
                    </div>
                </div>

                {{-- Description --}}
                @if($asset->description)
                <div class="pt-2 border-t border-gray-100">
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide mb-1.5">Description</p>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $asset->description }}</p>
                </div>
                @endif

                {{-- Stock Locations --}}
                @if($asset->stocks && $asset->stocks->count() > 0)
                <div class="pt-2 border-t border-gray-100">
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide mb-2">Stock Locations</p>
                    <div class="space-y-1.5">
                        @foreach($asset->stocks as $stock)
                        <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3.5 py-2.5">
                            <span class="text-sm font-medium text-gray-700">{{ $stock->location->name ?? 'N/A' }}</span>
                            <span class="text-sm text-gray-500">Qty: <strong>{{ $stock->quantity }}</strong></span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Current Assignment --}}
                @if($asset->assignments && $asset->assignments->count() > 0)
                <div class="pt-2 border-t border-gray-100">
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide mb-2">Current Assignment</p>
                    @foreach($asset->assignments as $assignment)
                    <div class="bg-blue-50 border border-blue-100 rounded-xl px-3.5 py-3 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-800">{{ $assignment->assignee->full_name ?? $assignment->assignee->name ?? 'N/A' }}</p>
                            @if($assignment->status === 'active')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">Active</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-500">{{ ucfirst($assignment->status) }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500">
                            From: {{ $assignment->assigned_date ?? 'N/A' }}
                            @if($assignment->expected_return_date)
                                &middot; Due: {{ $assignment->expected_return_date }}
                            @endif
                        </p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- ===== UPDATE CONDITION FORM ===== --}}
            @if(session('success'))
            <div class="px-5 sm:px-8 mb-2 no-print">
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            </div>
            @endif

            <div class="px-5 sm:px-8 pb-6 no-print">
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide mb-3">Report Condition</p>
                    <form method="POST" action="{{ route('asset.public.update-condition', $asset->asset_code) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <select name="condition" required
                                class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-[#128a43] focus:ring-1 focus:ring-[#128a43]/20 transition appearance-none">
                                <option value="good" {{ $asset->condition === 'good' ? 'selected' : '' }}>Good</option>
                                <option value="fair" {{ $asset->condition === 'fair' ? 'selected' : '' }}>Fair</option>
                                <option value="broken" {{ $asset->condition === 'broken' ? 'selected' : '' }}>Broken</option>
                                <option value="lost" {{ $asset->condition === 'lost' ? 'selected' : '' }}>Lost</option>
                            </select>
                            <select name="location_id" required
                                class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-[#128a43] focus:ring-1 focus:ring-[#128a43]/20 transition appearance-none">
                                <option value="">Select Location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                class="w-full px-4 py-2.5 bg-[#128a43] text-white text-sm font-semibold rounded-xl hover:brightness-110 active:brightness-90 transition shadow-sm">
                                Update
                            </button>
                        </div>
                        <textarea name="remark" rows="2" placeholder="Optional remark..."
                            class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-[#128a43] focus:ring-1 focus:ring-[#128a43]/20 transition resize-none placeholder-gray-300"></textarea>
                    </form>
                </div>
            </div>

            {{-- ===== FOOTER ===== --}}
            <div class="px-5 sm:px-8 py-4 bg-gray-50 border-t border-gray-100 no-print">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] text-gray-400">Scanned {{ now()->format('d M Y, h:i A') }}</p>
                    <p class="text-[11px] text-gray-400">PEPY Asset</p>
                </div>
            </div>

        </div>
    </div>

</body>
</html>