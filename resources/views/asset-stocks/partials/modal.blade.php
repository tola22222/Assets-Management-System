<div id="assetStockModal" class="fixed inset-0 hidden z-[100] overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">
            <div class="px-6 py-6 sm:px-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 id="assetModalTitle" class="text-xl font-bold text-slate-900">Add New Stock</h3>
                    <button onclick="closeModal()" class="p-2 hover:bg-slate-100 rounded-full text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form id="assetStockForm" method="POST" class="space-y-5">
                    @csrf
                    <div id="methodField"></div>

                    {{-- Asset --}}
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Asset</label>
                        <select name="asset_id" id="asset_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10" required>
                            <option value="">Select Asset</option>
                            @foreach($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Location --}}
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Location</label>
                        <select name="location_id" id="location_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10" required>
                            <option value="">Select Location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Quantity --}}
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Quantity</label>
                        <input type="number" name="quantity" id="quantity" min="0" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10" required>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-800 transition-colors">Discard</button>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-all shadow-md shadow-indigo-100">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
