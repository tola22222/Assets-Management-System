@extends('layouts.app') {{-- Ensure you have a layouts/app.blade.php --}}

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Asset Inventory Summary</h2>
        <button onclick="window.print()" class="btn btn-outline-primary shadow-sm">
            <i class="fa fa-print"></i> Print Report
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Asset Code</th>
                        <th>Name / Brand</th>
                        <th>Category</th>
                        <th>Condition</th>
                        <th>Location & Qty</th>
                        <th class="text-center">Total Stock</th>
                        <th class="pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr>
                            <td class="ps-4"><code>{{ $asset->asset_code }}</code></td>
                            <td>
                                <div>{{ $asset->name }}</div>
                                <small class="text-muted">{{ $asset->brand ?? 'No Brand' }} - {{ $asset->model ?? 'N/A' }}</small>
                            </td>
                            <td>{{ $asset->category->name ?? 'Uncategorized' }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $asset->condition == 'good' ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ ucfirst($asset->condition) }}
                                </span>
                            </td>
                            <td>
                                @forelse($asset->stocks as $stock)
                                    <div class="small">
                                        {{ $stock->location->name ?? 'Unknown' }}: <strong>{{ $stock->quantity }}</strong>
                                    </div>
                                @empty
                                    <span class="text-danger small">No location assigned</span>
                                @endforelse
                            </td>
                            <td class="text-center">
                                <span class="fw-bold">{{ $asset->stocks->sum('quantity') }}</span>
                            </td>
                            <td class="pe-4">
                                <span class="badge {{ $asset->status == 'active' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ ucfirst($asset->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No assets found in the system.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection