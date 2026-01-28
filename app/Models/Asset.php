<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code','name','category_id','description',
        'model','brand','serial_number','purchase_date',
        'purchase_price','condition','status'
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function stocks()
    {
        return $this->hasMany(AssetStock::class);
    }
    
}
