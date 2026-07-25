@extends('layouts.app')
@section('title', __('messages.system_settings'))
@section('content')
<div class="space-y-8">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('messages.system_settings') }}</h1>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ __('messages.organization_information') }}</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-8">
            <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-6">{{ __('messages.organization_information') }}</h3>
            <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.organization_name') }}</label>
                    <input type="text" name="organization_name" value="{{ $settings['organization_name'] ?? '' }}"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.system_name') }}</label>
                    <input type="text" name="system_name" value="{{ $settings['system_name'] ?? __('messages.app_name') }}"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.email') }}</label>
                        <input type="email" name="email" value="{{ $settings['email'] ?? '' }}"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.phone') }}</label>
                        <input type="text" name="phone" value="{{ $settings['phone'] ?? '' }}"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.address') }}</label>
                    <textarea name="address" rows="2"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200 resize-none">{{ $settings['address'] ?? '' }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.language') }}</label>
                        <select name="locale"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                            <option value="en" {{ ($settings['locale'] ?? 'en') === 'en' ? 'selected' : '' }}>{{ __('messages.en') }}</option>
                            <option value="km" {{ ($settings['locale'] ?? 'en') === 'km' ? 'selected' : '' }}>{{ __('messages.km') }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.theme_color') }}</label>
                        <input type="color" name="theme_color" value="{{ $settings['theme_color'] ?? '#128a43' }}"
                            class="w-full h-11 border border-gray-200 dark:border-gray-600 rounded-xl cursor-pointer p-1">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.qr_code_size') }}</label>
                        <input type="number" name="qr_size" value="{{ $settings['qr_size'] ?? '300' }}" min="100" max="1000"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">{{ __('messages.organization_logo') }}</label>
                    <input type="file" name="logo" accept="image/*"
                        class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand file:text-white hover:file:bg-brand-dark transition">
                    @if(isset($settings['logo']))
                        <img src="{{ Storage::url($settings['logo']) }}" class="h-16 mt-2 rounded-lg border border-gray-200 dark:border-gray-700">
                    @endif
                </div>
                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                    <button type="submit"
                        class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">{{ __('messages.save_settings') }}</button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-8">
            <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-6">{{ __('messages.appearance') }}</h3>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-300 text-sm">{{ __('messages.dark_mode') }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('messages.switch_light_dark') }}</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="darkModeToggle" class="sr-only peer" onchange="toggleDarkMode()">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                    <span id="modeLabel" class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.light') }}</span>
                </label>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-8">
            <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-6">Asset Count Report Schedule</h3>
            <form action="{{ route('settings.update') }}" method="POST" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Report Interval (months)</label>
                    <input type="number" name="report_interval_months" value="{{ $settings['report_interval_months'] ?? 6 }}" min="1" max="24"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                    <p class="text-xs text-gray-400 dark:text-gray-500">Default 6 — matches the Feb/Aug counting cycle. Finance Manager, Executive Director and Admin users are notified in-app and by email when a cycle is due.</p>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold">Next report due:</span>
                    {{ $nextReportDue ? $nextReportDue->format('d M Y') : 'On the next scheduled check (no report sent yet)' }}
                </div>
                <div class="pt-2">
                    <button type="submit"
                        class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">{{ __('messages.save_settings') }}</button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-8">
            <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-6">{{ __('messages.database_management') }}</h3>
            <div class="space-y-4">
                <form action="{{ route('settings.backup') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="w-full px-6 py-3 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition shadow-sm">
                        <svg class="w-5 h-5 inline mr-2 -mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        {{ __('messages.backup_database') }}
                    </button>
                </form>
                <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 text-sm">{{ __('messages.available_backups') }}</h4>
                    <div id="backupList" class="space-y-2">
                        <p class="text-gray-400 text-sm">{{ __('messages.no_backups') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleDarkMode() {
        const html = document.documentElement;
        const isDark = html.classList.toggle('dark');
        localStorage.setItem('darkMode', isDark);
        document.getElementById('modeLabel').innerText = isDark ? '{{ __("messages.dark") }}' : '{{ __("messages.light") }}';
    }
    document.getElementById('darkModeToggle').checked = localStorage.getItem('darkMode') === 'true';
    document.getElementById('modeLabel').innerText = document.getElementById('darkModeToggle').checked ? '{{ __("messages.dark") }}' : '{{ __("messages.light") }}';

    fetch('/settings/backups/list')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('backupList');
            if (data.length === 0) {
                list.innerHTML = '<p class="text-gray-400 text-sm">{{ __("messages.no_backups") }}</p>';
            } else {
                list.innerHTML = data.map(b => `
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                        <div>
                            <p class="text-sm font-semibold text-gray-700">${b.name}</p>
                            <p class="text-xs text-gray-400">${(b.size / 1024 / 1024).toFixed(2)} MB - ${b.date}</p>
                        </div>
                        <a href="/settings/restore/${b.name}" onclick="return confirm('{{ __("messages.restore") }}?')"
                           class="text-brand text-sm font-semibold hover:underline">{{ __("messages.restore") }}</a>
                    </div>
                `).join('');
            }
        });
</script>
@endsection
