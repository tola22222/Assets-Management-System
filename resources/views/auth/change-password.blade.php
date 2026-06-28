@extends('layouts.app')
@section('title', 'Change Password')
@section('content')
<div class="max-w-lg mx-auto space-y-8">
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl">{{ session('success') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-slate-800">Change Password</h1>
        <p class="text-slate-500">Update your account password.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <form action="{{ route('password.change') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Current Password</label>
                <input type="password" name="current_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                @error('current_password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">New Password</label>
                <input type="password" name="password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
            </div>
            <div class="pt-4">
                <button type="submit" class="px-6 py-2.5 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark">Change Password</button>
            </div>
        </form>
    </div>
</div>
@endsection
