<div id="assetStockModal" class="fixed inset-0 bg-gray-900/40 dark:bg-gray-950/60 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">Receive Assets</h3>
            <button onclick="closeModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="assetStockForm" method="POST" action="{{ route('asset-stocks.store') }}" class="flex-1 overflow-y-auto">
            @csrf
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <p class="text-xs text-gray-500 dark:text-gray-400">Each unit received gets its own Asset ID, tag, and QR code — quantity creates that many individual asset records, not a shared counter.</p>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Asset Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. HP ProBook 450"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:border-brand transition">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Category <span class="text-red-500">*</span></label>
                        <select name="category_id" required
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Location <span class="text-red-500">*</span></label>
                        <select name="location_id" required
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                            <option value="">Select Location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" min="1" max="200" value="1" required
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Brand</label>
                        <input type="text" name="brand"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Model</label>
                        <input type="text" name="model"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Purchase Date</label>
                        <input type="date" name="purchase_date"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Purchase Price (each)</label>
                        <input type="number" step="0.01" name="purchase_price"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Condition</label>
                        <select name="condition"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="broken">Broken</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="closeModal()"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <button type="submit"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">Receive</button>
            </div>
        </form>
    </div>
</div>

<style>
.animate__slide-in-right { animation: slideInRight 0.2s ease-out; }
@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>
