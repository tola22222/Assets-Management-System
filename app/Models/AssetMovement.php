<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetMovement extends Model
{
    // app/Models/AssetMovement.php

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
