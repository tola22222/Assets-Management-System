<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AMS Cambodia - @yield('title')</title>

    {{-- Favicon - All Sizes --}}
    <link rel="icon" type="image/png" sizes="16x16"   href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32"   href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96"   href="{{ asset('favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180"      href="{{ asset('favicon-180x180.png') }}">
    <link rel="shortcut icon"                          href="{{ asset('favicon.ico') }}">

    {{-- Tailwind CDN + Custom Brand Color --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#128a43',
                            light:   '#16a84f',
                            dark:    '#0e6b34',
                            50:      '#f0fdf4',
                            100:     '#dcfce7',
                        }
                    }
                }
            }
        }
    </script>

    {{-- Google Font --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Alpine.js --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="bg-slate-50 text-slate-900">
    <div class="flex min-h-screen">

        {{-- SIDEBAR --}}
        @include('partials.sidebar')

        {{-- MAIN CONTENT --}}
        <div class="flex-1 flex flex-col">

            {{-- HEADER --}}
            @include('partials.header')

            {{-- PAGE CONTENT --}}
            <main class="p-8">
                @yield('content')
            </main>

        </div>
    </div>
</body>
</html>