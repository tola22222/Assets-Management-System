@extends('layouts.app')
@section('title', $category->name)
@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6">
    <a href="{{ route('categories.index') }}" class="text-sm text-brand font-bold hover:underline inline-block mb-6">&larr; Back</a>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">{{ $category->name }}</h1>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Short Name</p>
                <p class="font-bold text-slate-700">{{ $category->short_name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Assets Count</p>
                <p class="font-bold text-slate-700">{{ $category->assets_count ?? 0 }}</p>
            </div>
        </div>
        @if($category->description)
        <div class="mt-6">
            <p class="text-xs text-slate-400 uppercase font-bold">Description</p>
            <p class="text-slate-600 mt-1">{{ $category->description }}</p>
        </div>
        @endif
        <div class="flex flex-col sm:flex-row gap-3 mt-8">
            <a href="{{ route('categories.edit', $category) }}" class="w-full sm:w-auto px-6 py-2.5 bg-brand text-white rounded-xl text-sm font-bold hover:bg-brand-dark text-center">Edit Category</a>
            <a href="{{ route('categories.index') }}" class="w-full sm:w-auto px-6 py-2.5 border border-slate-300 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 text-center">Back</a>
        </div>
    </div>
</div>
@endsection
