<div x-show="open" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak>
    <div @click.away="open = false" class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden border border-white/20">
        <form :action="action" method="POST" class="p-8">
            @csrf
            <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

            <h2 class="text-2xl font-black text-slate-900 mb-6" x-text="editMode ? 'Edit Stock' : 'Add Stock Entry'"></h2>

            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">Select Asset</label>
                    <select name="asset_id" x-model="form.asset_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 outline-none focus:ring-4 focus:ring-indigo-500/10">
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">Location</label>
                    <select name="location_id" x-model="form.location_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 outline-none focus:ring-4 focus:ring-indigo-500/10">
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">Quantity</label>
                    <input type="number" name="quantity" x-model="form.quantity" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 outline-none focus:ring-4 focus:ring-indigo-500/10">
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8">
                <button type="button" @click="open = false" class="px-6 py-3 font-bold text-slate-400">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 transition" x-text="editMode ? 'Update Inventory' : 'Save Entry'"></button>
            </div>
        </form>
    </div>
</div>