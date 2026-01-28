<div id="assetRegistrationModal"
     class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-3xl rounded-[2.5rem] shadow-2xl overflow-hidden">

        {{-- HEADER --}}
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-black text-slate-800">
                Register New Physical Asset
            </h2>
            <button onclick="closeModal('assetRegistrationModal')"
                class="text-slate-400 hover:text-slate-700 text-2xl font-bold">
                &times;
            </button>
        </div>

        {{-- FORM --}}
        <form action="{{ route('assets.store') }}"
              method="POST"
              enctype="multipart/form-data"
              class="p-8 space-y-6">
            @csrf

            {{-- IDENTIFICATION --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h3 class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">
                        Identification
                    </h3>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">
                            Product Model
                        </label>
                        <select name="product_id" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm">
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">
                            Asset Tag
                        </label>
                        <input type="text" name="asset_tag" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">
                            Serial Number
                        </label>
                        <input type="text" name="serial_number" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-mono">
                    </div>
                </div>

                {{-- FINANCIAL --}}
                <div class="space-y-4">
                    <h3 class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">
                        Financial
                    </h3>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">
                            Purchase Date
                        </label>
                        <input type="date" name="purchase_date"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">
                            Purchase Cost
                        </label>
                        <input type="number" step="0.01" name="purchase_cost"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">
                            Warranty Expiry
                        </label>
                        <input type="date" name="warranty_expiry"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm">
                    </div>
                </div>
            </div>

            {{-- STATUS --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Condition</label>
                    <select name="condition" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm">
                        <option value="New">New</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Broken">Broken</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Status</label>
                    <select name="status" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm">
                        <option value="In Stock">In Stock</option>
                        <option value="In Use">In Use</option>
                        <option value="In Transit">In Transit</option>
                        <option value="Disposed">Disposed</option>
                    </select>
                </div>
            </div>

            {{-- FILES --}}
            <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="file" name="invoice">
                    <input type="file" name="image">
                    <input type="file" name="warranty_doc">
                </div>
            </div>

            {{-- ACTIONS --}}
            <div class="flex gap-3 pt-4">
                <button type="button"
                    onclick="closeModal('assetRegistrationModal')"
                    class="flex-1 px-6 py-3 rounded-2xl font-bold text-slate-400 hover:bg-slate-50">
                    Cancel
                </button>

                <button type="submit"
                    class="flex-[2] bg-slate-900 text-white px-6 py-3 rounded-2xl font-bold hover:bg-indigo-600">
                    Confirm Registration
                </button>
            </div>

        </form>
    </div>
</div>
