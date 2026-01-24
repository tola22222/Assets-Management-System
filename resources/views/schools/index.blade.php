@extends('layouts.app')

@section('title', 'Manage Schools')

@section('content')
<div x-data="{ 
    open: false, 
    editMode: false, 
    form: { id: '', name: '', address: '', contact_person: '', phone: '', status: 'Active', end_date: '' } 
}">

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Schools</h1>
                <p class="text-slate-500 text-sm">Manage educational institutions receiving equipment support.</p>
            </div>
            <button @click="open = true; editMode = false; form = { id:'', name:'', address:'', contact_person:'', phone:'', status:'Active', end_date:'' }" 
                    class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg hover:bg-indigo-700 transition">
                + Register New School
            </button>
        </div>

        <!-- SCHOOL CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($schools as $school)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden hover:border-indigo-300 transition group">
                <div class="h-32 bg-slate-100 relative">
                    <div class="absolute inset-0 flex items-center justify-center text-slate-300">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="absolute top-4 right-4">
                        <span class="bg-white/90 backdrop-blur px-3 py-1 rounded-full text-[10px] font-bold text-indigo-600 uppercase">
                            {{ $school->province ?? 'Unknown' }}
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-slate-800 text-lg group-hover:text-indigo-600 transition">
                        {{ $school->name }}
                    </h3>
                    <p class="text-slate-500 text-sm mt-1 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        </svg>
                        {{ $school->address }}
                    </p>

                    <div class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-50 pt-4">
                        <div>
                            <p class="text-[10px] uppercase font-bold text-slate-400">Total Assets</p>
                            <p class="text-lg font-bold text-slate-700">{{ $school->assets_count ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-slate-400">Status</p>
                            <p class="text-sm font-bold {{ $school->status == 'Active' ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $school->status ?? 'Active' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-2">
                        <button class="flex-1 bg-slate-50 text-slate-600 py-2 rounded-lg text-sm font-semibold hover:bg-slate-100 transition">View Details</button>
                        
                        <button @click="open = true; editMode = true; form = {
                            id: {{ $school->id }},
                            name: '{{ $school->name }}',
                            address: '{{ $school->address }}',
                            contact_person: '{{ $school->contact_person }}',
                            phone: '{{ $school->phone }}',
                            status: '{{ $school->status }}',
                            end_date: '{{ $school->end_date }}'
                        }"
                        class="px-3 bg-slate-50 text-slate-600 py-2 rounded-lg hover:bg-slate-100 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @include('schools.partials.modal')

</div>
@endsection
