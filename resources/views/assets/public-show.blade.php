<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Detail - {{ $asset->asset_code }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-indigo-200 text-xs font-bold uppercase tracking-widest">Asset Information</p>
                        <h1 class="text-2xl font-black mt-1">{{ $asset->name }}</h1>
                        <p class="text-indigo-200 font-mono text-sm mt-1">{{ $asset->asset_code }}</p>
                    </div>
                    <span class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $asset->status === 'active' ? 'bg-emerald-400/20 text-emerald-200' : 'bg-slate-400/20 text-slate-200' }}">
                        {{ strtoupper($asset->status) }}
                    </span>
                </div>
            </div>

            <div class="p-8">
                <div class="flex gap-8">
                    <div class="flex-shrink-0 text-center">
                        @if($asset->qr_code_url)
                            <img src="{{ $asset->qr_code_url }}" alt="QR Code" class="w-32 h-32 mx-auto border border-slate-100 rounded-xl p-2">
                        @endif
                        @if($asset->image_url)
                            <img src="{{ $asset->image_url }}" alt="{{ $asset->name }}" class="w-32 h-32 object-cover rounded-xl border border-slate-200 mt-2">
                        @endif
                    </div>

                    <div class="flex-1 space-y-4">
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Asset Code</dt>
                                <dd class="text-sm font-mono text-slate-800 mt-0.5">{{ $asset->asset_code }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Category</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">{{ $asset->category->name ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Brand</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">{{ $asset->brand ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Model</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">{{ $asset->model ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Serial Number</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">{{ $asset->serial_number ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Condition</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">{{ ucfirst($asset->condition) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Purchase Date</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">{{ $asset->purchase_date ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Purchase Price</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">${{ number_format($asset->purchase_price, 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400 font-bold uppercase">Location</dt>
                                <dd class="text-sm text-slate-800 mt-0.5">{{ $asset->location ?? 'N/A' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs text-slate-400 font-bold uppercase">Status</dt>
                                <dd class="text-sm mt-0.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $asset->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ strtoupper($asset->status) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        @if($asset->description)
                        <div class="pt-3 border-t border-slate-100">
                            <dt class="text-xs text-slate-400 font-bold uppercase mb-1">Description</dt>
                            <dd class="text-sm text-slate-600">{{ $asset->description }}</dd>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between no-print">
                    <p class="text-xs text-slate-400">
                        Scanned on {{ now()->format('d M Y, h:i A') }}
                    </p>
                    <button onclick="window.print()"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm">
                        Print Details
                    </button>
                </div>
            </div>

            <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 text-center no-print">
                <p class="text-xs text-slate-400">
                    Powered by <span class="font-bold text-slate-600">AMS Cambodia</span> &mdash; Asset Management System
                </p>
            </div>
        </div>
    </div>
</body>
</html>
