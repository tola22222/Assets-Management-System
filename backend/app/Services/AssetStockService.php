<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock of an asset MODEL for the Transfer and Assignment flows, counted per
 * LOCATION — the Office and every school hold their own stock.
 *
 * A model is every register unit with the same name and category — the same
 * grouping as the "Assets by Model" report, which this does not change. Each
 * unit sits at one location (assets.location_id), so a location's stock is
 * simply the units there:
 *
 *   total       units of the model at the location (not disposed)
 *   lost_broken units there a verification marked lost or broken
 *   transferred units there that are out: held by a current assignment (to a
 *               staff member or program, made by an accepted transfer or the
 *               Edit dialog) plus units on a transfer leaving the location
 *               that is still waiting to be accepted or rejected
 *   available   total - lost_broken - transferred, never below zero
 *
 * Office has 10, sends 2 to Kralanh: while it waits Office shows 10 / 2 out /
 * 8 available; once accepted Office has 8 and Kralanh 2. Kralanh assigns both:
 * Kralanh total 2, transferred 2, available 0 — and cannot send any on.
 *
 * Nothing here is stored. Every number is recounted from the units, transfers
 * and assignments themselves, so it updates by itself after every transfer,
 * acceptance, rejection, return or assignment edit.
 */
class AssetStockService
{
    /** Available at or below this shows as Low Stock. */
    public const LOW_STOCK_AT = 10;

    /** Assignment statuses that still hold units ("returned" gives them back). */
    private const HOLDING_ASSIGNMENT_STATUSES = ['assigned', 'active', 'overdue'];

    /**
     * @param  int[]|null  $locationIds  the location(s) whose stock to count —
     *                                   normally one; null counts every site
     */
    public function forAsset(Asset $asset, ?int $ignoreAssignmentId = null, ?array $locationIds = null): array
    {
        $ids = $this->modelUnitIds($asset, $locationIds);

        $total = count($ids);
        $lostBroken = Asset::whereIn('id', $ids)->whereIn('condition', Asset::UNAVAILABLE_CONDITIONS)->count();

        // Assignments on units at this location. A unit keeps its assignments
        // when it moves, so they always count where the unit now is.
        $assigned = (int) AssetAssignment::whereIn('asset_id', $ids)
            ->whereIn('status', self::HOLDING_ASSIGNMENT_STATUSES)
            ->when($ignoreAssignmentId, fn ($q) => $q->where('id', '!=', $ignoreAssignmentId))
            ->sum('quantity');

        // A transfer holds its units at the source while it waits (they only
        // change location when accepted). Rejected, they are free again;
        // accepted, they are the destination's stock — held there only if the
        // transfer named a recipient (the assignment above, never twice). A
        // return leg completes on the spot, so it never holds anything.
        $pending = (int) AssetTransfer::whereIn('asset_id', $ids)
            ->whereIn('status', AssetTransfer::OPEN_STATUSES)
            ->whereNull('parent_transfer_id')
            ->sum('quantity');

        $out = $assigned + $pending;
        $available = max(0, $total - $lostBroken - $out);

        return [
            'name' => $asset->name,
            'total' => $total,
            'lost_broken' => $lostBroken,
            'assigned' => $assigned,
            'pending' => $pending,
            'transferred' => $out,
            'available' => $available,
            'low_stock' => $available <= self::LOW_STOCK_AT,
            // The exact units free to tick on a transfer from this location:
            // usable, not held by anyone, not already travelling.
            'available_ids' => $this->freeUnitIds($ids, $ignoreAssignmentId),
        ];
    }

    /**
     * Refuse a quantity larger than what is available at the location(s).
     * Locks the model's units first, so two requests racing for the last
     * units cannot both pass — call it inside the same DB transaction that
     * writes the row.
     *
     * @param  int[]|null  $locationIds  the location the units are taken from
     */
    public function assertAvailable(Asset $asset, int $quantity, string $field = 'quantity', ?int $ignoreAssignmentId = null, ?array $locationIds = null): void
    {
        Asset::whereIn('id', $this->modelUnitIds($asset, $locationIds))->lockForUpdate()->get(['id']);

        $stock = $this->forAsset($asset, $ignoreAssignmentId, $locationIds);

        if ($quantity > $stock['available']) {
            $where = $this->locationLabel($locationIds);

            throw ValidationException::withMessages([
                $field => "Only {$stock['available']} of \"{$asset->name}\" available{$where} (total {$stock['total']}, transferred {$stock['transferred']}"
                    .($stock['lost_broken'] ? ", lost/broken {$stock['lost_broken']}" : '').") — you asked for {$quantity}.",
            ]);
        }
    }

    /**
     * Of these units, the ones held by a current assignment of their own.
     *
     * @param  int[]  $unitIds
     * @return int[]
     */
    public function heldUnitIds(array $unitIds, ?int $ignoreAssignmentId = null): array
    {
        if ($unitIds === []) {
            return [];
        }

        return AssetAssignment::whereIn('asset_id', $unitIds)
            ->whereIn('status', self::HOLDING_ASSIGNMENT_STATUSES)
            ->when($ignoreAssignmentId, fn ($q) => $q->where('id', '!=', $ignoreAssignmentId))
            ->pluck('asset_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @param  int[]  $ids  units of one model at one location
     * @return int[] the ones usable, unassigned and not on an open transfer
     */
    private function freeUnitIds(array $ids, ?int $ignoreAssignmentId = null): array
    {
        if ($ids === []) {
            return [];
        }

        $usable = Asset::whereIn('id', $ids)->available()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $travelling = AssetTransfer::whereIn('status', AssetTransfer::OPEN_STATUSES)->whereIn('asset_id', $ids)->pluck('asset_id')
            ->merge(DB::table('asset_transfer_items')
                ->join('asset_transfers', 'asset_transfers.id', '=', 'asset_transfer_items.asset_transfer_id')
                ->whereIn('asset_transfers.status', AssetTransfer::OPEN_STATUSES)
                ->whereIn('asset_transfer_items.asset_id', $ids)
                ->pluck('asset_transfer_items.asset_id'))
            ->map(fn ($id) => (int) $id)->all();

        return array_values(array_diff($usable, $this->heldUnitIds($ids, $ignoreAssignmentId), $travelling));
    }

    /** " at Kralanh High School", for the refusal message. */
    private function locationLabel(?array $locationIds): string
    {
        if ($locationIds === null || count($locationIds) !== 1) {
            return '';
        }

        $name = DB::table('locations')->where('id', $locationIds[0])->value('name');

        return $name ? " at {$name}" : '';
    }

    /**
     * @param  int[]|null  $locationIds  only units at these locations; null
     *                                   counts every site
     * @return int[] ids of the register units of this asset's model
     */
    private function modelUnitIds(Asset $asset, ?array $locationIds = null): array
    {
        return Asset::onRegister()
            ->where('name', $asset->name)
            ->where('category_id', $asset->category_id)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
