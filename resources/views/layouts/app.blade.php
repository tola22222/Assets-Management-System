<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMS Cambodia - @yield('title')</title>

    <link rel="icon" type="image/png" sizes="16x16"        href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32"        href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96"        href="{{ asset('favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="1080x1080"     href="{{ asset('Favicon1080x1080.png') }}">
    <link rel="apple-touch-icon" sizes="180x180"           href="{{ asset('favicon-180x180.png') }}">
    <link rel="apple-touch-icon" sizes="1080x1080"          href="{{ asset('Favicon1080x1080.png') }}">
    <link rel="shortcut icon"                               href="{{ asset('favicon.ico') }}">

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '{{ $appSettings['theme_color'] ?? '#128a43' }}',
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

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Kantumruy Pro', 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>

</head>

<body class="bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-gray-300 antialiased" x-data="{ sidebarOpen: false }">
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-40 lg:hidden"></div>
    <div x-show="sidebarOpen" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 z-50 w-64 lg:hidden">
        @include('partials.sidebar')
    </div>

    <div class="flex min-h-screen">
        <div class="hidden lg:block lg:w-64 lg:flex-shrink-0">
            @include('partials.sidebar')
        </div>

        <div class="flex-1 flex flex-col min-w-0">
            @include('partials.header')
            <main class="p-6 lg:p-8 overflow-y-auto">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
