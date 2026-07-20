@extends('layouts.app')
@section('title', 'Data Completeness Report')
@section('content')
@include('reports._table', ['title' => 'Data Completeness', 'route' => 'reports.data-completeness', 'headers' => ['Asset Code', 'Name', 'Category', 'Missing Fields'], 'rows' => $assets, 'cols' => function($a) { return [$a->asset_code, $a->name, $a->category->name ?? 'N/A', $a->missing_fields]; }])
@endsection
