<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    /**
     * Icons a category can be shown with — keys of the app's own icon set
     * (frontend/src/utils/categoryIcons.js, taken from the sidebar's icons).
     * No icon means the default box.
     */
    public const ICONS = ['assets', 'activity', 'truck', 'cog', 'setup', 'clipboard', 'home', 'cap', 'shield', 'chart', 'users'];

    protected $fillable = [
        'name',
        'description',
        'short_name',
        'icon',
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'category_id');
    }
}
