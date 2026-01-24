<?php

namespace App\Http\Controllers;
use App\Models\{PurchaseOrder, PurchaseOrderItem, Supplier, Product, ActivityLog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function show(PurchaseOrder $purchaseOrder)
    {
        // Load relationships
        $po = $purchaseOrder->load(['supplier', 'items.product.category']);
        
        return view('purchase_orders.show_modal', compact('po'))->render();
    }

    public function index()
    {
        $pos = PurchaseOrder::with('supplier')->latest()->paginate(10);
        $suppliers = Supplier::all();
        $products = Product::all();
        return view('purchase_orders.index', compact('pos', 'suppliers', 'products'));
    }

   public function store(Request $request)
{
    // 1. Auto-generate PO Number (Format: PO-YYYYMMDD-001)
    $today = now()->format('Ymd');
    $countToday = PurchaseOrder::whereDate('created_at', now()->toDateString())->count() + 1;
    $autoPoNumber = 'PO-' . $today . '-' . str_pad($countToday, 3, '0', STR_PAD_LEFT);

    // 2. Add to request data so validation passes
    $request->merge(['po_number' => $autoPoNumber]);

    $request->validate([
        'supplier_id' => 'required|exists:suppliers,id',
        'po_number' => 'required|unique:purchase_orders,po_number',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.price' => 'required|numeric|min:0',
    ]);
    

    DB::transaction(function () use ($request) {
        $total = 0;
        
        $po = PurchaseOrder::create([
            'supplier_id' => $request->supplier_id,
            'po_number' => $request->po_number,
            'status' => 'Pending',
            'total_amount' => 0 
        ]);

        foreach ($request->items as $item) {
            $subtotal = $item['quantity'] * $item['price'];
            $total += $subtotal;

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
        }

        $po->update(['total_amount' => $total]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'Create',
            'description' => "Created PO: {$po->po_number}"
        ]);
    });

    return redirect()->route('purchase-orders.index')->with('success', 'PO Created Successfully!');
}

}