@extends('layouts.app')
@section('title', 'Verification Report')
@section('content')
@include('reports._table', ['title' => 'Verification Report', 'route' => 'reports.verifications', 'headers' => ['Asset', 'Verified By', 'Condition', 'Date', 'Remark'], 'rows' => $verifications, 'cols' => function($v) { return [$v->asset->name ?? 'N/A', $v->verifiedBy->name ?? 'N/A', ucfirst($v->condition), $v->verified_at ? $v->verified_at->format('d M Y') : 'N/A', $v->remark ?? 'N/A']; }])
@endsection
