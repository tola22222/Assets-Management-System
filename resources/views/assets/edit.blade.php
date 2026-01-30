@extends('layouts.app')

@section('title', 'Edit Asset')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Edit Asset: {{ $asset->asset_code }}</h1>
            <p class="text-slate-500 font-medium">Update the details for this specific asset.</p>
        </div>
        <a href="{{ route('assets.index') }}" class="text-slate-600 hover:text-slate-900 font-medium underline">Back to List</a>
    </div>

    <form action="{{ route('assets.update', $asset->id) }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')
        
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-800 mb-6">General Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Asset Code</label>
                    <input type="text" name="asset_code" value="{{ old('asset_code', $asset->asset_code) }}" required
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Asset Name</label>
                    <input type="text" name="name" value="{{ old('name', $asset->name) }}" required
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Category</label>
                    <select name="category_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 outline-none">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ $asset->category_id == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 outline-none">
                        @foreach(['active', 'maintenance', 'broken', 'disposed'] as $status)
                            <option value="{{ $status }}" {{ $asset->status == $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-4">
            <button type="submit" class="px-10 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg transition-all">
                Update Asset Details
            </button>
        </div>
    </form>
</div>
@endsection