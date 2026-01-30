<div x-show="open" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak>
    <div @click.away="open = false" class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden border border-white/20">
        <form :action="action" method="POST" class="p-8 space-y-6">
            @csrf
            <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

            <h2 class="text-2xl font-black text-slate-900" x-text="editMode ? 'Edit Location' : 'New Location'"></h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">Location Name</label>
                    <input type="text" name="name" x-model="form.name" required placeholder="e.g. Finance Office"
                        class="w-full px-5 py-4 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">Category Type</label>
                    <select name="type" x-model="form.type" required class="w-full px-5 py-4 rounded-2xl border border-slate-200 bg-slate-50 outline-none">
                        <option value="office">Office</option>
                        <option value="lab">ICT Lab</option>
                        <option value="program">Program Department</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="open = false" class="px-6 py-4 font-bold text-slate-400">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-10 py-4 rounded-2xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition transform active:scale-95" 
                        x-text="editMode ? 'Update' : 'Save Location'"></button>
            </div>
        </form>
    </div>
</div>