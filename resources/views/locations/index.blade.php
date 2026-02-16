@extends('layouts.app')

@section('content')

<x-page-header
    title="Assets Locations"
    subtitle="Manage offices, labs, and program departments."
    buttonText="Add Location"
    buttonAction="openModal()"
/>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @foreach($locations as $location)
    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-all">
        <div class="flex justify-between items-start mb-4">
            <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest
                {{ $location->type == 'lab' ? 'bg-purple-100 text-purple-600' : ($location->type == 'program' ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-600') }}">
                {{ $location->type }}
            </span>
            <div class="flex gap-2">
                <button onclick='initForm(@json($location))' class="text-slate-400 hover:text-indigo-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <form action="{{ route('assets-locations.destroy', $location->id) }}" method="POST" onsubmit="return confirm('Delete this location?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        </div>
        <h3 class="text-xl font-bold text-slate-800">{{ $location->name }}</h3>
    </div>
    @endforeach
</div>

<!-- Modal -->
<div id="locationModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm hidden">
    <div id="modalContent" class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden border border-white/20">
        <form id="locationForm" method="POST" class="p-8 space-y-6">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <h2 id="modalTitle" class="text-2xl font-black text-slate-900">New Location</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">Location Name</label>
                    <input type="text" name="name" id="locationName" required placeholder="e.g. Finance Office"
                        class="w-full px-5 py-4 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">Category Type</label>
                    <select name="type" id="locationType" required class="w-full px-5 py-4 rounded-2xl border border-slate-200 bg-slate-50 outline-none">
                        <option value="office">Office</option>
                        <option value="lab">ICT Lab</option>
                        <option value="program">Program Department</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="px-6 py-4 font-bold text-slate-400">Cancel</button>
                <button type="submit" id="submitBtn" class="bg-indigo-600 text-white px-10 py-4 rounded-2xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition transform active:scale-95">Save Location</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('locationModal');
    const form = document.getElementById('locationForm');
    const modalTitle = document.getElementById('modalTitle');
    const formMethod = document.getElementById('formMethod');
    const locationName = document.getElementById('locationName');
    const locationType = document.getElementById('locationType');
    const submitBtn = document.getElementById('submitBtn');

    function openModal() {
        modal.classList.remove('hidden');
        modalTitle.textContent = 'New Location';
        formMethod.value = 'POST';
        form.action = "{{ route('assets-locations.store') }}";
        locationName.value = '';
        locationType.value = 'office';
        submitBtn.textContent = 'Save Location';
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function initForm(location) {
        modal.classList.remove('hidden');
        modalTitle.textContent = 'Edit Location';
        formMethod.value = 'PUT';
        form.action = `/assets-locations/${location.id}`;
        locationName.value = location.name;
        locationType.value = location.type;
        submitBtn.textContent = 'Update';
    }

    // Close modal when clicking outside content
    document.getElementById('modalContent').addEventListener('click', function(e) {
        e.stopPropagation();
    });
    modal.addEventListener('click', closeModal);
</script>

@endsection
