<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asset->name }} — {{ $asset->asset_code }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/Favicon1080x1080.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/Favicon1080x1080.png') }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .card-shadow { box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.06); }
        /* Tailwind's .flex outranks the browser's own [hidden] rule, so the banners
           toggled by the script at the bottom would otherwise never hide. */
        [hidden] { display: none !important; }
        @media print { .no-print { display: none !important; } body { background: #fff !important; } }
    </style>
</head>
<body class="bg-[#f5f7fa] min-h-dvh flex flex-col">

    {{-- Top bar --}}
    <header class="sticky top-0 z-20 bg-white border-b border-gray-100 px-4 h-14 flex items-center justify-between no-print">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background:#128a43">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <span class="text-sm font-semibold text-gray-800 truncate">{{ $asset->name }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold px-2.5 py-1 rounded-md {{ $asset->status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500' }}">
                {{ strtoupper($asset->status) }}
            </span>
        </div>
    </header>

    <main class="flex-1 px-4 py-5 max-w-lg mx-auto w-full">

        {{-- Hero card --}}
        <div class="bg-white rounded-2xl card-shadow overflow-hidden mb-4">
            @if($asset->image_url)
            <div class="aspect-[3/1] bg-gray-100 overflow-hidden">
                <img src="{{ $asset->image_url }}" alt="{{ $asset->name }}" class="w-full h-full object-cover">
            </div>
            @else
            <div class="aspect-[3/1] flex items-center justify-center" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7)">
                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.41a2.25 2.25 0 013.182 0l2.909 2.91m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
            </div>
            @endif
            <div class="px-5 py-4 space-y-3">
                <div>
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Asset Code</p>
                    <p class="text-base font-mono font-bold text-gray-900 mt-0.5">{{ $asset->asset_code }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Category</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $asset->category->name ?? 'N/A' }}</p>
                    </div>
                    <div class="flex-1">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Condition</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ ucfirst($asset->condition) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detail grid --}}
        <div class="bg-white rounded-2xl card-shadow overflow-hidden mb-4 divide-y divide-gray-50">
            <div class="px-5 py-3.5 flex items-center justify-between">
                <span class="text-sm text-gray-500">Brand</span>
                <span class="text-sm font-semibold text-gray-900">{{ $asset->brand ?? 'N/A' }}</span>
            </div>
            <div class="px-5 py-3.5 flex items-center justify-between">
                <span class="text-sm text-gray-500">Model</span>
                <span class="text-sm font-semibold text-gray-900">{{ $asset->model ?? 'N/A' }}</span>
            </div>
            <div class="px-5 py-3.5 flex items-center justify-between">
                <span class="text-sm text-gray-500">Serial Number</span>
                <span class="text-sm font-mono font-semibold text-gray-900">{{ $asset->serial_number ?? 'N/A' }}</span>
            </div>
            <div class="px-5 py-3.5 flex items-center justify-between">
                <span class="text-sm text-gray-500">Purchase Date</span>
                <span class="text-sm font-semibold text-gray-900">{{ $asset->purchase_date ?? 'N/A' }}</span>
            </div>
            <div class="px-5 py-3.5 flex items-center justify-between">
                <span class="text-sm text-gray-500">Purchase Price</span>
                <span class="text-sm font-semibold text-gray-900">{{ $asset->purchase_price ? '$'.number_format($asset->purchase_price, 2) : 'N/A' }}</span>
            </div>
        </div>

        {{-- Description --}}
        @if($asset->description)
        <div class="bg-white rounded-2xl card-shadow overflow-hidden mb-4 px-5 py-4">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Description</p>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $asset->description }}</p>
        </div>
        @endif

        {{-- Location + Assignment --}}
        @if($asset->location || ($asset->assignments && $asset->assignments->count() > 0))
        <div class="bg-white rounded-2xl card-shadow overflow-hidden mb-4 divide-y divide-gray-50">
            @if($asset->location)
            <div class="px-5 py-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2.5">Current Location</p>
                <div class="bg-gray-50 rounded-xl px-3.5 py-2.5">
                    <span class="text-sm font-medium text-gray-700">{{ $asset->location->name }}</span>
                </div>
            </div>
            @endif
            @if($asset->assignments && $asset->assignments->count() > 0)
            <div class="px-5 py-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2.5">Current Assignment</p>
                @foreach($asset->assignments as $assignment)
                <div class="bg-blue-50/60 border border-blue-100 rounded-xl px-4 py-3 space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-800">{{ $assignment->assignee->full_name ?? $assignment->assignee->name ?? 'N/A' }}</p>
                        @if($assignment->status === 'active')
                            <span class="shrink-0 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-600">Active</span>
                        @else
                            <span class="shrink-0 px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-500">{{ ucfirst($assignment->status) }}</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500">
                        From {{ $assignment->assigned_date ?? 'N/A' }}
                        @if($assignment->expected_return_date) &middot; Due {{ $assignment->expected_return_date }} @endif
                    </p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- Report Condition — the original form, for signed-in users only.
             The page is public, so it starts on the sign-in card. The script at the
             bottom swaps in the form when this browser holds the SPA's login token
             (same origin, so the same localStorage), records the scan, and submits
             the form to /api/qr-scan/{code}/verify under that account. Nothing on
             this page can change an asset without a valid token. --}}
        <div id="verify-success" hidden class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-sm flex items-center gap-2.5 mb-4">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Condition updated successfully.
        </div>
        <div id="verify-error" hidden class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm flex items-center gap-2.5 mb-4">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span id="verify-error-text"></span>
        </div>

        <div id="verify-signin" class="bg-white rounded-2xl card-shadow overflow-hidden mb-4 no-print">
            <div class="px-5 py-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-3">Report Condition</p>
                <a href="/app/login?return={{ urlencode('/asset/'.$asset->asset_code) }}"
                    class="block w-full py-3 bg-[#128a43] text-white text-sm font-bold rounded-xl hover:brightness-110 active:brightness-90 transition shadow-sm text-center">
                    Sign in to update
                </a>
            </div>
        </div>

        <div id="verify-form-card" hidden class="bg-white rounded-2xl card-shadow overflow-hidden mb-4 no-print">
            <div class="px-5 py-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-3">Report Condition</p>
                <form id="verify-form" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2.5">
                        <select name="condition" required
                            class="w-full bg-gray-50 border-0 rounded-xl px-3.5 py-2.5 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#128a43]/30 transition">
                            <option value="good" {{ $asset->condition === 'good' ? 'selected' : '' }}>Good</option>
                            <option value="fair" {{ $asset->condition === 'fair' ? 'selected' : '' }}>Fair</option>
                            <option value="broken" {{ $asset->condition === 'broken' ? 'selected' : '' }}>Broken</option>
                            <option value="lost" {{ $asset->condition === 'lost' ? 'selected' : '' }}>Lost</option>
                        </select>
                        {{-- Starts on the recorded location: leaving it verifies the asset
                             there, choosing another one updates where it is. --}}
                        <select name="location_id" required
                            class="w-full bg-gray-50 border-0 rounded-xl px-3.5 py-2.5 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#128a43]/30 transition">
                            <option value="" disabled {{ $asset->location_id ? '' : 'selected' }}>Location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ (int) $asset->location_id === $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <textarea name="remark" rows="2" placeholder="Add a remark (optional)..."
                        class="w-full bg-gray-50 border-0 rounded-xl px-3.5 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#128a43]/30 transition resize-none placeholder-gray-400"></textarea>
                    <button type="submit"
                        class="w-full py-3 bg-[#128a43] text-white text-sm font-bold rounded-xl hover:brightness-110 active:brightness-90 transition shadow-sm">
                        Update Condition
                    </button>
                </form>
            </div>
        </div>

        {{-- QR Actions --}}
        @if($asset->qr_code_url)
        <div class="bg-white rounded-2xl card-shadow overflow-hidden mb-4 no-print">
            <div class="px-5 py-4 flex items-center gap-4">
                <div class="border border-gray-100 rounded-xl p-1.5 bg-white shadow-sm shrink-0">
                    <img src="{{ $asset->qr_code_url }}" alt="QR" class="w-14 h-14">
                </div>
                <div class="flex gap-2 flex-1">
                    <button onclick="window.print()"
                        class="flex-1 py-2.5 bg-gray-900 text-white text-sm font-semibold rounded-xl hover:bg-gray-800 transition text-center">
                        Print
                    </button>
                    <a href="{{ $asset->qr_code_url }}" download="{{ $asset->asset_code }}-qr.png"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-200 transition text-center">
                        Download PNG
                    </a>
                </div>
            </div>
        </div>
        @endif

    </main>

    {{-- Footer --}}
    <footer class="px-4 py-4 border-t border-gray-100 bg-white no-print">
        <div class="max-w-lg mx-auto flex items-center justify-between">
            <p class="text-[11px] text-gray-400">Scanned {{ now()->format('d M Y, h:i A') }}</p>
            <p class="text-[11px] font-semibold text-gray-400">PEPY Asset</p>
        </div>
    </footer>

    <script>
    (function () {
        var code = @json($asset->asset_code);
        var flashKey = 'asset-verified:' + code;
        var signin = document.getElementById('verify-signin');
        var formCard = document.getElementById('verify-form-card');
        var form = document.getElementById('verify-form');
        var success = document.getElementById('verify-success');
        var error = document.getElementById('verify-error');
        var errorText = document.getElementById('verify-error-text');
        var token = null;

        // Storage can throw outright (private mode, blocked site data). Treat
        // that as "not signed in" rather than breaking the page.
        try { token = localStorage.getItem('token'); } catch (e) {}

        function showForm(signedIn) {
            signin.hidden = signedIn;
            formCard.hidden = !signedIn;
        }

        function fail(message) {
            errorText.textContent = message;
            error.hidden = false;
        }

        // An expired or revoked token: forget it, exactly as the SPA does on a 401.
        function signedOut() {
            try { localStorage.removeItem('token'); localStorage.removeItem('user'); } catch (e) {}
            showForm(false);
        }

        function post(path, body) {
            return fetch('/api' + path, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: 'Bearer ' + token },
                body: JSON.stringify(body),
            });
        }

        function messageFrom(data, fallback) {
            var first = data && data.errors && Object.values(data.errors)[0];
            return (first && first[0]) || (data && data.message) || fallback;
        }

        try {
            if (sessionStorage.getItem(flashKey)) {
                sessionStorage.removeItem(flashKey);
                success.hidden = false;
            }
        } catch (e) {}

        if (!token) return;
        showForm(true);

        // Records who scanned this tag. The server folds a reload within two
        // minutes into the same scan, so the post-submit refresh doesn't double up.
        post('/qr-scan', { asset_code: code }).then(function (res) {
            if (res.status === 401) return signedOut();
            if (res.status === 404) {
                formCard.hidden = true;
                fail('This asset belongs to another site, so you cannot update it.');
            }
        }).catch(function () {});

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            error.hidden = true;

            post('/qr-scan/' + encodeURIComponent(code) + '/verify', {
                condition: form.elements.condition.value,
                location_id: form.elements.location_id.value,
                remark: form.elements.remark.value,
            }).then(function (res) {
                if (res.status === 401) { button.disabled = false; return signedOut(); }
                return res.json().catch(function () { return null; }).then(function (data) {
                    if (!res.ok) {
                        button.disabled = false;
                        return fail(messageFrom(data, 'Could not update the condition. Please try again.'));
                    }
                    // Reload so the details above show the saved condition/location.
                    try { sessionStorage.setItem(flashKey, '1'); } catch (e) {}
                    window.location.reload();
                });
            }).catch(function () {
                button.disabled = false;
                fail('Could not reach the server. Check your connection and try again.');
            });
        });
    })();
    </script>

</body>
</html>