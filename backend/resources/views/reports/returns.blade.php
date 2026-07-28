@extends('layouts.app')
@section('title', 'Return Report')
@section('content')
@include('reports._table', ['title' => 'Return Report', 'route' => 'reports.returns', 'headers' => ['Asset', 'Returned By', 'Condition', 'Date', 'Status'], 'rows' => $returns, 'cols' => function($r) { return [$r->asset->name ?? 'N/A', $r->returnedBy->name ?? 'N/A', ucfirst($r->condition), $r->return_date ? $r->return_date->format('d M Y') : 'N/A', strtoupper($r->status)]; }])
@endsection
