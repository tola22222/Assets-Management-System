<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PEPY Asset Management</title>

    <link rel="icon" type="image/png" href="{{ asset('images/Favicon1080x1080.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/Favicon1080x1080.png') }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '#128a43', light: '#16a84f', dark: '#0e6b34', '50': '#f0fdf4', '100': '#dcfce7' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center py-12">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-slate-800">Forgot Password</h1>
            <p class="text-slate-500 mt-1">Enter your email to receive a reset link.</p>
        </div>

        @if(session('status'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4">{{ session('status') }}</div>
        @endif

        <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full px-6 py-2.5 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark">Send Reset Link</button>
            <p class="text-center text-sm text-slate-500 mt-4">
                <a href="{{ route('login') }}" class="text-brand font-bold hover:underline">Back to Login</a>
            </p>
        </form>
    </div>
</body>
</html>
