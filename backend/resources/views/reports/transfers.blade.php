@extends('layouts.app')
@section('title', 'Transfer Report')
@section('content')
@include('reports._table', ['title' => 'Transfer Report', 'route' => 'reports.transfers', 'headers' => ['Asset', 'From', 'To', 'Date', 'Status'], 'rows' => $transfers, 'cols' => function($t) { return [$t->asset->name ?? 'N/A', $t->fromLocation->name ?? 'N/A', $t->toLocation->name ?? 'N/A', $t->transfer_date ? $t->transfer_date->format('d M Y') : 'N/A', strtoupper($t->status)]; }])
@endsection
