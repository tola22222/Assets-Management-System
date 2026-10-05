<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    /**
     * Icons a category can be shown with — keys of the app's own icon set
     * (frontend/src/utils/categoryIcons.js: the sidebar's icons plus
     * Heroicons' computer and wrench). No icon means one is chosen from its
     * name.
     */
    public const ICONS = ['computer', 'assets', 'activity', 'truck', 'cog', 'setup', 'clipboard', 'home', 'cap', 'shield', 'wrench', 'chart', 'users'];

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
