<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PEPY Asset Management</title>

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

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 flex items-center justify-center min-h-screen antialiased">
    <div class="w-full max-w-md p-4 sm:p-6">

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
            <div class="text-center mb-8">
                <img src="{{ asset('images/Favicon1080x1080.png') }}" alt="PEPY Logo" class="h-24 w-auto mx-auto mb-4 object-contain">
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">PEPY Asset Management</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Sign in to manage assets</p>
            </div>

            @if($errors->any())
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg text-sm mb-4">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-5">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 rounded-lg p-2.5 text-sm focus:outline-none focus:border-brand transition"
                        placeholder="admin@ams.com">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password</label>
                    <input type="password" name="password" required
                        class="w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 rounded-lg p-2.5 text-sm focus:outline-none focus:border-brand transition"
                        placeholder="password123">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 dark:border-gray-600 text-brand focus:ring-brand">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Remember me</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-brand font-semibold hover:underline">Forgot Password?</a>
                </div>
                <button type="submit" class="w-full bg-brand hover:bg-brand-dark text-white py-2.5 rounded-lg font-semibold text-sm shadow-sm transition tracking-wide">
                    Login
                </button>
            </form>
        </div>

    </div>
</body>
</html>
