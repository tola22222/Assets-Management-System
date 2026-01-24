<div x-show="open" 
     class="fixed inset-0 z-50 overflow-y-auto" 
     x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        
        <div x-show="open" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             class="fixed inset-0 transition-opacity bg-slate-900/50 backdrop-blur-sm"></div>

        <div x-show="open" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             class="inline-block w-full max-w-lg my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-bold text-slate-800" x-text="editMode ? 'Update School' : 'Register New School'"></h3>
                <button @click="open = false" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="editMode ? `/schools/${form.id}` : '/schools'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">School Name</label>
                        <input type="text" name="name" x-model="form.name" required
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Address (Province/District)</label>
                        <input type="text" name="address" x-model="form.address"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Contact Person</label>
                            <input type="text" name="contact_person" x-model="form.contact_person"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Phone Number</label>
                            <input type="text" name="phone" x-model="form.phone"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                            <select name="status" x-model="form.status"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                <option value="Active">Active</option>
                                <option value="Ended">Ended</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Project End Date</label>
                            <input type="date" name="end_date" x-model="form.end_date"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" @click="open = false" 
                            class="flex-1 px-4 py-2.5 border border-slate-200 text-slate-600 rounded-xl font-semibold hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition"
                            x-text="editMode ? 'Update School' : 'Save School'">
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>