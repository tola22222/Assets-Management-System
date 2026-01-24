<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AMS Cambodia</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-indigo-600">AMS Cambodia</h1>
            <p class="text-slate-500">Sign in to manage assets</p>
        </div>

        <form action="{{ route('login') }}" method="POST" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required 
                           class="w-full mt-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Password</label>
                    <input type="password" name="password" required 
                           class="w-full mt-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold hover:bg-indigo-700 transition">
                    Login
                </button>
            </div>
        </form>
    </div>
</body>
</html>