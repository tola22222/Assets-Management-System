@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-xl">{{ session('success') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-slate-800 dark:text-white">My Profile</h1>
        <p class="text-slate-500 dark:text-gray-400">Manage your account information.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <div class="p-8">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <div class="flex items-center gap-6">
                    <img src="{{ $user->photo_url }}" alt="Photo" class="h-20 w-20 rounded-full border-2 border-brand-100">
                    <div>
                        <p class="font-bold text-slate-800 dark:text-white text-lg">{{ $user->name }}</p>
                        <p class="text-slate-500 dark:text-gray-400">{{ $user->email }}</p>
                        <p class="text-xs text-slate-400 dark:text-gray-500 capitalize">{{ $user->role }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Full Name</label>
                    <input type="text" name="name" value="{{ $user->name }}" required class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ $user->phone }}" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Profile Photo</label>
                    <input type="file" name="photo" accept="image/*" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>

                <div class="flex items-center gap-4 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark">Update Profile</button>
                    <a href="{{ route('password.change') }}" class="px-6 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl text-sm font-bold text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-800">Change Password</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
