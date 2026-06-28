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
        'purchase_price','condition','status','image_path',
        'qr_code_path'
    ];

    protected $appends = ['image_url', 'qr_code_url'];

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function getQrCodeUrlAttribute()
    {
        return $this->qr_code_path ? asset('storage/' . $this->qr_code_path) : null;
    }

    public function getPublicUrlAttribute()
    {
        return route('asset.public.show', $this->asset_code);
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function stocks()
    {
        return $this->hasMany(AssetStock::class);
    }

    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function verifications()
    {
        return $this->hasMany(AssetVerification::class);
    }

    public function transfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

}
