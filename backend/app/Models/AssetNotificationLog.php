<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetNotificationLog extends Model
{
    protected $fillable = [
        'event_type', 'asset_code', 'recipient', 'subject', 'status', 'error', 'attempts',
    ];
}
