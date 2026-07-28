@extends('layouts.app')
@section('title', 'QR Scan Report')
@section('content')
@include('reports._table', ['title' => 'QR Scan Report', 'route' => 'reports.qr-scans', 'headers' => ['User', 'Message', 'Date'], 'rows' => $scans, 'cols' => function($s) { return [$s->user->name ?? 'N/A', $s->message, $s->created_at->format('d M Y H:i')]; }])
@endsection
