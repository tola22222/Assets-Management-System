<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'asset_id', 'quantity', 'price'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // This allows you to show which asset was purchased in reports
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
