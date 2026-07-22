@extends('layouts.app')
@section('title', 'Location Report')
@section('content')
@include('reports._table', ['title' => 'Location Report', 'route' => 'reports.locations', 'headers' => ['Name', 'School', 'Type', 'Assets Count'], 'rows' => $locations, 'cols' => function($l) { return [$l->name, $l->school->name ?? 'N/A', ucfirst($l->type), $l->assets_count]; }])
@endsection
