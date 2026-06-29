<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asset->name }} - {{ $asset->asset_code }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            {{-- Header --}}
            <div class="bg-brand px-8 py-6 text-white" style="background-color: #128a43;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/60 text-xs font-bold uppercase tracking-widest">Asset Information</p>
                        <h1 class="text-2xl font-black mt-1">{{ $asset->name }}</h1>
                        <p class="text-white/70 font-mono text-sm mt-1">{{ $asset->asset_code }}</p>
                    </div>
                    <span class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $asset->status === 'active' ? 'bg-white/20 text-white' : 'bg-gray-400/20 text-gray-200' }}">
                        {{ strtoupper($asset->status) }}
                    </span>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-8">
                <div class="flex gap-8">
                    {{-- Left: Images --}}
                    <div class="flex-shrink-0 text-center space-y-3">
                        @if($asset->image_url)
                            <img src="{{ $asset->image_url }}" alt="{{ $asset->name }}"
                                class="w-40 h-36 object-cover rounded-xl border border-gray-200 shadow-sm">
                        @else
                            <div class="w-40 h-36 bg-gray-100 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 text-xs">No Image</div>
                        @endif
                        @if($asset->qr_code_url)
                            <div class="border border-gray-200 rounded-xl p-2 bg-white inline-block">
                                <img src="{{ $asset->qr_code_url }}" alt="QR Code" class="w-28 h-28">
                            </div>
                            <div class="flex items-center justify-center gap-3 no-print">
                                <button onclick="window.print()"
                                    class="px-3 py-1.5 bg-brand text-white text-xs font-semibold rounded-xl hover:brightness-110 transition shadow-sm" style="background-color: #128a43;">
                                    Print
                                </button>
                                <a href="{{ route('assets.download-qr', $asset->id) }}"
                                    class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition shadow-sm">
                                    Download PNG
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- Right: Details --}}
                    <div class="flex-1 space-y-5">
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Asset Code</dt>
                                <dd class="text-sm font-mono text-gray-800 mt-0.5 font-medium">{{ $asset->asset_code }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Category</dt>
                                <dd class="text-sm text-gray-800 mt-0.5 font-medium">{{ $asset->category->name ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Brand</dt>
                                <dd class="text-sm text-gray-800 mt-0.5 font-medium">{{ $asset->brand ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Model</dt>
                                <dd class="text-sm text-gray-800 mt-0.5 font-medium">{{ $asset->model ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Serial Number</dt>
                                <dd class="text-sm text-gray-800 mt-0.5 font-medium">{{ $asset->serial_number ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Condition</dt>
                                <dd class="text-sm text-gray-800 mt-0.5 font-medium">{{ ucfirst($asset->condition) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Purchase Date</dt>
                                <dd class="text-sm text-gray-800 mt-0.5 font-medium">{{ $asset->purchase_date ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Purchase Price</dt>
                                <dd class="text-sm text-gray-800 mt-0.5 font-medium">{{ $asset->purchase_price ? '$'.number_format($asset->purchase_price, 2) : 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide">Status</dt>
                                <dd class="text-sm mt-0.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $asset->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ strtoupper($asset->status) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        @if($asset->description)
                            <div class="pt-3 border-t border-gray-100">
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide mb-1">Description</dt>
                                <dd class="text-sm text-gray-600 leading-relaxed">{{ $asset->description }}</dd>
                            </div>
                        @endif

                        {{-- Stock Info --}}
                        @if($asset->stocks && $asset->stocks->count() > 0)
                            <div class="pt-3 border-t border-gray-100">
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide mb-2">Stock Locations</dt>
                                <div class="space-y-1.5">
                                    @foreach($asset->stocks as $stock)
                                        <div class="flex items-center justify-between text-sm bg-gray-50 rounded-lg px-3 py-2">
                                            <span class="text-gray-700 font-medium">{{ $stock->location->name ?? 'N/A' }}</span>
                                            <span class="text-gray-500">Qty: {{ $stock->quantity }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Current Assignment --}}
                        @if($asset->assignments && $asset->assignments->count() > 0)
                            <div class="pt-3 border-t border-gray-100">
                                <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide mb-2">Current Assignment</dt>
                                @foreach($asset->assignments as $assignment)
                                    <div class="text-sm bg-gray-50 rounded-lg px-3 py-2 space-y-1">
                                        <p class="text-gray-700 font-medium">Assigned to: {{ $assignment->assignee->full_name ?? $assignment->assignee->name ?? 'N/A' }}</p>
                                        <p class="text-gray-500 text-xs">From: {{ $assignment->assigned_date ?? 'N/A' }} @if($assignment->expected_return_date) | Due: {{ $assignment->expected_return_date }} @endif</p>
                                        @if($assignment->status === 'active')
                                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Active</span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-600">{{ ucfirst($assignment->status) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Update Condition --}}
            @if(session('success'))
                <div class="px-8 pb-2 no-print">
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm">{{ session('success') }}</div>
                </div>
            @endif
            <div class="px-8 pb-8 no-print">
                <div class="pt-4 border-t border-gray-100">
                    <dt class="text-xs text-gray-400 font-bold uppercase tracking-wide mb-3">Update Condition & Remark</dt>
                    <form method="POST" action="{{ route('asset.public.update-condition', $asset->asset_code) }}" class="space-y-3">
                        @csrf
                        <div class="flex items-center gap-3">
                            <select name="condition" required
                                class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#128a43] transition">
                                <option value="good" {{ $asset->condition === 'good' ? 'selected' : '' }}>Good</option>
                                <option value="fair" {{ $asset->condition === 'fair' ? 'selected' : '' }}>Fair</option>
                                <option value="broken" {{ $asset->condition === 'broken' ? 'selected' : '' }}>Broken</option>
                                <option value="lost" {{ $asset->condition === 'lost' ? 'selected' : '' }}>Lost</option>
                            </select>
                            <select name="location_id" required
                                class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#128a43] transition">
                                <option value="">Select Location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                class="px-4 py-2 bg-[#128a43] text-white text-sm font-semibold rounded-xl hover:brightness-110 transition shadow-sm">
                                Update
                            </button>
                        </div>
                        <div>
                            <textarea name="remark" rows="2" placeholder="Optional remark..."
                                class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#128a43] transition resize-none"></textarea>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-8 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between no-print">
                <p class="text-xs text-gray-400">
                    Scanned on {{ now()->format('d M Y, h:i A') }}
                </p>
                <p class="text-xs text-gray-400">
                    Powered by <span class="font-bold text-gray-600">PEPY Asset</span>
                </p>
            </div>
        </div>
    </div>
</body>
</html>