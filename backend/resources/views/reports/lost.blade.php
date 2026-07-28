@extends('layouts.app')
@section('title', 'Lost Assets Report')
@section('content')
@include('reports._table', ['title' => 'Lost Assets', 'route' => 'reports.lost', 'headers' => ['Asset Code', 'Name', 'Category', 'Last Updated'], 'rows' => $assets, 'cols' => function($a) { return [$a->asset_code, $a->name, $a->category->name ?? 'N/A', $a->updated_at->format('d M Y')]; }])
@endsection
