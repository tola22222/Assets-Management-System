<?php

namespace App\Models;

use App\Services\PermissionRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A custom role: a named bundle of Module -> Permission grants that can be
 * assigned to any number of users.
 *
 * Four rows are flagged is_system and mirror the built-in `users.role` values.
 * They can be renamed and re-permissioned but never deleted or deactivated,
 * because `users.role` still points at their slugs.
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'is_system'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected $appends = ['users_count_cached'];

    public function permissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    /** Only ever set by the accessor below when the relation is loaded. */
    public function getUsersCountCachedAttribute(): ?int
    {
        return $this->attributes['users_count'] ?? ($this->relationLoaded('users') ? $this->users->count() : null);
    }

    /** ['module' => ['view','create', ...]] */
    public function grants(): array
    {
        $out = [];

        foreach ($this->permissions as $permission) {
            $out[$permission->module][] = $permission->ability;
        }

        return PermissionRegistry::normalise($out);
    }

    /**
     * Replaces the role's whole grant set in one go. Normalising first means a
     * caller cannot store "delete without view", which the matrix treats as
     * impossible and the effective-permission check would misread.
     */
    public function syncGrants(array $grants): void
    {
        $clean = PermissionRegistry::normalise($grants);

        $this->permissions()->delete();

        $rows = [];
        foreach ($clean as $module => $abilities) {
            foreach ($abilities as $ability) {
                $rows[] = [
                    'role_id' => $this->id,
                    'module' => $module,
                    'ability' => $ability,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            RolePermission::insert($rows);
        }

        $this->unsetRelation('permissions');
    }

    public static function makeSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
