<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'event_type',
        'asset_id',
        'recipient_user_id',
        'status',
        'error',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
