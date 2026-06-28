@extends('layouts.app')
@section('title', 'Add Category')
@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6">
    <a href="{{ route('categories.index') }}" class="text-sm text-brand font-bold hover:underline inline-block mb-6">&larr; Back to Categories</a>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Add New Category</h1>
        <form action="{{ route('categories.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Name *</label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Short Name</label>
                <input type="text" name="short_name" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none" placeholder="e.g., LAP">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-brand outline-none"></textarea>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 pt-4">
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark text-center">Save Category</button>
                <a href="{{ route('categories.index') }}" class="w-full sm:w-auto px-6 py-2.5 border border-slate-300 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 text-center">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
