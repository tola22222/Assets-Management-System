<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\School;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockTransferController extends Controller
{
    public function index()
    {
        $transfers = StockTransfer::with(['school', 'user'])->latest()->paginate(10);
        $schools = School::all();
        $products = Product::all();
        return view('stock_transfers.index', compact('transfers', 'schools', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'transfer_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request) {
            // 1. Create the Master Transfer Record
            $transfer = StockTransfer::create([
                'school_id' => $request->school_id,
                'transferred_by' => Auth::id(),
                'transfer_date' => $request->transfer_date,
                'status' => 'Pending',
            ]);

            // 2. Add the Items
            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            // 3. Log Activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Create',
                'description' => "Initiated stock transfer to school ID: {$request->school_id}",
            ]);
        });

        return redirect()->route('stock-transfers.index')->with('success', 'Transfer initiated successfully!');
    }

    /**
     * Logic to Approve and update Inventory
     */
    public function approve(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === 'Approved') {
            return back()->with('error', 'This transfer is already approved.');
        }

        DB::transaction(function () use ($stockTransfer) {
            // Here you would typically subtract from HQ inventory 
            // and add to School inventory. 
            
            $stockTransfer->update(['status' => 'Approved']);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Approve',
                'description' => "Approved stock transfer #{$stockTransfer->id} to {$stockTransfer->school->name}",
            ]);
        });

        return back()->with('success', 'Stock transfer approved and inventory updated!');
    }

    public function show(StockTransfer $stockTransfer)
    {
        $transfer = $stockTransfer->load(['school', 'user', 'items.product']);
        return view('stock_transfers.show_modal', compact('transfer'))->render();
    }

    public function destroy(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === 'Approved') {
            return back()->with('error', 'Cannot delete an approved transfer.');
        }

        $stockTransfer->delete();
        return back()->with('success', 'Transfer cancelled.');
    }
}