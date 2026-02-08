<div id="assetModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-6xl overflow-hidden flex flex-col border border-white/20">
        
        {{-- Header --}}
        <div class="px-8 py-5 border-b border-slate-100 flex justify-between items-center bg-white">
            <h2 id="modalTitle" class="text-lg font-black text-slate-900 tracking-tight">Register New Asset</h2>
            <button onclick="closeAssetModal()" class="text-slate-300 hover:text-red-500 transition-colors text-2xl">&times;</button>
        </div>

        <form id="assetForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">

            {{-- Form Body --}}
            <div class="p-8">
                <div class="grid grid-cols-3 gap-10">
                    
                    {{-- Column 1: Identity --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-1.5 h-4 bg-indigo-500 rounded-full"></span>
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Identity</h3>
                        </div>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Asset Name *</label>
                                <input type="text" name="name" id="field_name" required
                                    class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border-none focus:ring-2 focus:ring-indigo-500/10 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Category</label>
                                    <select name="category_id" id="field_category_id" required class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border-none text-sm focus:ring-2 focus:ring-indigo-500/10">
                                        <option value="">Select...</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Status</label>
                                    <select name="status" id="field_status" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border-none text-sm font-bold text-indigo-600">
                                        <option value="active">Active</option>
                                        <option value="disposed">Disposed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Column 2: Specs --}}
                    <div class="space-y-4 border-x border-slate-100 px-10">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-1.5 h-4 bg-slate-300 rounded-full"></span>
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Specifications</h3>
                        </div>

                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Brand</label>
                                    <input type="text" name="brand" id="field_brand"
                                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border-none text-sm">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Model</label>
                                    <input type="text" name="model" id="field_model"
                                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border-none text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Serial Number</label>
                                <input type="text" name="serial_number" id="field_serial_number"
                                    class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border-none text-sm">
                            </div>
                        </div>
                    </div>

                    {{-- Column 3: Financials --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-1.5 h-4 bg-emerald-500 rounded-full"></span>
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Acquisition</h3>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Purchase Date</label>
                                <input type="date" name="purchase_date" id="field_purchase_date"
                                    class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border-none text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase">Purchase Price</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">$</span>
                                    <input type="number" step="0.01" name="purchase_price" id="field_purchase_price"
                                        class="w-full pl-8 pr-4 py-2.5 rounded-xl bg-slate-50 border-none text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-8 py-6 border-t border-slate-100 flex justify-end items-center gap-3 bg-slate-50/30">
                <button type="button" onclick="closeAssetModal()" 
                    class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-all">
                    Discard Changes
                </button>
                <button type="submit" id="submitBtn"
                    class="px-10 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-xl shadow-lg shadow-indigo-100 transition-all active:scale-95">
                    Save Asset
                </button>
            </div>
        </form>
    </div>
</div>