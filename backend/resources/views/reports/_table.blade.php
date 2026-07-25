<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">{{ $title }}</h1>
            <p class="text-slate-500 dark:text-gray-400">Total: {{ $rows->count() }} records</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route($route, ['export' => 'csv']) }}" class="px-4 py-2 border border-slate-200 dark:border-gray-700 rounded-xl text-sm font-bold text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-800">Export CSV</a>
            <button onclick="window.print()" class="px-4 py-2 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark">Print</button>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 dark:bg-gray-900">
                <tr>
                    @foreach($headers as $h)
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-gray-400 uppercase">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-gray-700">
                @forelse($rows as $row)
                <tr>
                    @foreach($cols($row) as $cell)
                    <td class="px-6 py-4 text-sm dark:text-gray-300">{{ $cell }}</td>
                    @endforeach
                </tr>
                @empty
                <tr><td colspan="{{ count($headers) }}" class="px-6 py-10 text-center text-slate-400 dark:text-gray-500">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
