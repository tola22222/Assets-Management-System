<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesAndSorts;
use App\Http\Controllers\Controller;
use App\Models\AssetMovement;
use Illuminate\Http\Request;

class AssetMovementController extends Controller
{
    use PaginatesAndSorts;

    private const SORTABLE = ['movement_type', 'reference_no', 'created_at'];

    public function index(Request $request)
    {
        $query = AssetMovement::with(['asset', 'fromLocation', 'toLocation']);

        $this->applySearch($query, $request, ['reference_no'], [
            'asset' => ['name', 'asset_code'],
        ]);
        $this->applyExactFilters($query, $request, ['movement_type']);

        return response()->json($this->paginateSorted($query, $request, self::SORTABLE));
    }

    public function destroy(AssetMovement $asset_movement)
    {
        $asset_movement->delete();

        return response()->json(['message' => 'Movement record deleted.']);
    }
}
