@extends('layouts.app')

@section('content')
<div x-data="{
    open: false,
    editMode: false,
    action: '',
    form: { id: '', name: '', type: 'office' },
    initForm(data = null) {
        if(data) {
            this.editMode = true;
            this.action = '/locations/' + data.id;
            this.form = { ...data };
        } else {
            this.editMode = false;
            this.action = '{{ route('assets-locations.store') }}';
            this.form = { id: '', name: '', type: 'office' };
        }
        this.open = true;
    }
}" class="space-y-6">

     <x-page-header
        title="Assets Locations"
        subtitle="Manage offices, labs, and program departments."
        buttonText="Add Location"
        buttonAction="initForm()"
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
                    <button @click="initForm({{ json_encode($location) }})" class="text-slate-400 hover:text-indigo-600 transition-colors">
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

    @include('locations.partials.modal')
</div>
@endsection
