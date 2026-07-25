@extends('layouts.app')
@section('title', 'Register Asset')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6">
    <a href="{{ route('assets.index') }}" class="text-sm text-brand font-bold hover:underline inline-block mb-6">&larr; Back</a>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-white mb-6">Register New Asset</h1>
        <form action="{{ route('assets.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Asset Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Category *</label>
                    <select name="category_id" required class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200">
                        <option value="">Select Category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Location *</label>
                    <select name="location_id" required class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200">
                        <option value="">Select Location</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Brand</label>
                    <input type="text" name="brand" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Model</label>
                    <input type="text" name="model" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Serial Number</label>
                    <input type="text" name="serial_number" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Condition</label>
                    <select name="condition" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200">
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="broken">Broken</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Status *</label>
                    <select name="status" required class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200">
                        <option value="active">Active</option>
                        <option value="disposed">Disposed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Purchase Date</label>
                    <input type="date" name="purchase_date" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Purchase Price</label>
                    <input type="number" step="0.01" name="purchase_price" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Asset Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-gray-300 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-500"></textarea>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 pt-4">
                <button type="submit" class="w-full sm:w-auto px-8 py-2.5 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark text-center">Register Asset</button>
                <a href="{{ route('assets.index') }}" class="w-full sm:w-auto px-6 py-2.5 border border-slate-300 dark:border-gray-600 rounded-xl text-sm font-bold text-slate-600 dark:text-gray-300 hover:bg-slate-50 dark:hover:bg-gray-700 text-center">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
