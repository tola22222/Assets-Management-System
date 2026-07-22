@extends('layouts.app')
@section('title', 'Assets by Model')
@section('content')
@include('reports._table', [
    'title' => 'Assets by Model',
    'route' => 'reports.by-model',
    'headers' => ['Model', 'Category', 'Total Units', 'Stock Level'],
    'rows' => $rows,
    'cols' => function ($row) {
        return [
            $row->name,
            $row->category->name ?? 'N/A',
            $row->total,
            ucfirst($row->stock_level),
        ];
    },
])
@endsection
