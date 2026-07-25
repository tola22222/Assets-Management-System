<div class="p-6">
    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4">Stock Transfer Details</h3>
    <div class="space-y-3">
        <div><p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Reference</p><p class="font-bold dark:text-gray-200">{{ $stockTransfer->reference ?? 'N/A' }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">From</p><p class="font-bold dark:text-gray-200">{{ $stockTransfer->fromSchool->name ?? 'N/A' }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">To</p><p class="font-bold dark:text-gray-200">{{ $stockTransfer->toSchool->name ?? 'N/A' }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-gray-500 uppercase font-bold">Date</p><p class="font-bold dark:text-gray-200">{{ $stockTransfer->created_at ? $stockTransfer->created_at->format('d M Y') : 'N/A' }}</p></div>
    </div>
</div>
