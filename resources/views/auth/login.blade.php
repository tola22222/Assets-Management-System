<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PEPY Asset Management</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/png" sizes="16x16"   href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32"   href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96"   href="{{ asset('favicon-96x96.png') }}">
    <link rel="apple-touch-icon" sizes="180x180"      href="{{ asset('favicon-180x180.png') }}">
    <link rel="shortcut icon"                          href="{{ asset('favicon.ico') }}">

    {{-- Tailwind CDN + Brand Color --}}
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

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6">

        {{-- LOGO --}}
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <img src="{{ asset('images/logo.png') }}" alt="PEPY Logo" class="h-24 w-24 object-contain">
            </div>
            <h1 class="text-2xl font-bold text-slate-800">PEPY Asset Management</h1>
            <p class="text-slate-500 text-sm mt-1">Sign in to manage assets</p>
        </div>

        <form action="{{ route('login') }}" method="POST" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full mt-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand outline-none text-sm">
                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Password</label>
                    <input type="password" name="password" required
                        class="w-full mt-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand outline-none text-sm">
                </div>
                <button type="submit" class="w-full bg-brand text-white py-2.5 rounded-lg font-bold hover:bg-brand-dark transition text-sm">
                    Login
                </button>
            </div>
        </form>

    </div>
</body>
</html>