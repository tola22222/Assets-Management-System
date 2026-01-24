<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AssetTrack Cambodia - @yield('title')</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Alpine.js (REQUIRED FOR MODAL FIX) -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- IMPORTANT: Prevent modal auto popup -->
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="bg-slate-50 text-slate-900">

    <div class="flex min-h-screen">

        <!-- SIDEBAR -->
        @include('partials.sidebar')

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col">

            <!-- HEADER -->
            @include('partials.header')

            <!-- PAGE CONTENT -->
            <main class="p-8">
                @yield('content')
            </main>

        </div>
    </div>

</body>
</html>
