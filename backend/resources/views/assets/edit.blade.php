@extends('layouts.app')
@section('title', 'Edit Asset')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6">
    <a href="{{ route('assets.index') }}" class="text-sm text-brand font-bold hover:underline inline-block mb-6">&larr; Back</a>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Asset: {{ $asset->asset_code }}</h1>
        <form action="{{ route('assets.update', $asset) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Asset Name *</label>
                    <input type="text" name="name" value="{{ $asset->name }}" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Category *</label>
                    <select name="category_id" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $asset->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Location *</label>
                    <select name="location_id" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ $asset->location_id == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Brand</label>
                    <input type="text" name="brand" value="{{ $asset->brand }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Model</label>
                    <input type="text" name="model" value="{{ $asset->model }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Serial Number</label>
                    <input type="text" name="serial_number" value="{{ $asset->serial_number }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Condition</label>
                    <select name="condition" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                        <option value="good" {{ $asset->condition == 'good' ? 'selected' : '' }}>Good</option>
                        <option value="fair" {{ $asset->condition == 'fair' ? 'selected' : '' }}>Fair</option>
                        <option value="broken" {{ $asset->condition == 'broken' ? 'selected' : '' }}>Broken</option>
                        <option value="lost" {{ $asset->condition == 'lost' ? 'selected' : '' }}>Lost</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Status *</label>
                    <select name="status" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                        <option value="active" {{ $asset->status == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="disposed" {{ $asset->status == 'disposed' ? 'selected' : '' }}>Disposed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Purchase Date</label>
                    <input type="date" name="purchase_date" value="{{ $asset->purchase_date }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Purchase Price</label>
                    <input type="number" step="0.01" name="purchase_price" value="{{ $asset->purchase_price }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Asset Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
                    @if($asset->image_path) <p class="text-xs text-slate-400 mt-1">Current: {{ basename($asset->image_path) }}</p> @endif
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">{{ $asset->description }}</textarea>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 pt-4">
                <button type="submit" class="w-full sm:w-auto px-8 py-2.5 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark text-center">Update Asset</button>
                <a href="{{ route('assets.index') }}" class="w-full sm:w-auto px-6 py-2.5 border border-slate-300 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 text-center">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
