@extends('layouts.app')
@section('title', 'Disposed Assets Report')
@section('content')
@include('reports._table', ['title' => 'Disposed Assets', 'route' => 'reports.disposed', 'headers' => ['Asset Code', 'Name', 'Category', 'Disposed'], 'rows' => $assets, 'cols' => function($a) { return [$a->asset_code, $a->name, $a->category->name ?? 'N/A', $a->updated_at->format('d M Y')]; }])
@endsection
