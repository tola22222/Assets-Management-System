<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = ['role_id', 'module', 'ability'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
