<?php

namespace App\Models;

use App\Services\PermissionRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'staff_id',
        'phone',
        'photo_path',
        'is_active',
        'is_locked',
        'last_login_at',
        'receive_reports',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'receive_reports' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isOperationsHrManager(): bool
    {
        return $this->role === 'operations_hr_manager';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isExecutiveDirector(): bool
    {
        return $this->role === 'executive_director';
    }

    public function isFinanceManager(): bool
    {
        return $this->role === 'finance_manager';
    }

    public function canApproveDisposal(): bool
    {
        return $this->isExecutiveDirector();
    }

    // ---- Site scope ------------------------------------------------------
    //
    // Staff only ever see and act on their own site; OPM, Finance and the ED
    // see every site. A staff account with no site set yet is unrestricted
    // until someone sets it (fail OPEN, by design — failing closed would
    // strand every staff user whose site was never filled in). Every
    // staff-facing query goes through these helpers so that rule is the same
    // on the register, search, QR scan and the lists.

    public function isSiteScoped(): bool
    {
        return $this->isStaff();
    }

    /** The staff member's site, or null (only meaningful when isSiteScoped()). */
    public function siteLocationId(): ?int
    {
        $locationId = $this->staff?->location_id;

        return $locationId === null ? null : (int) $locationId;
    }

    /** True when this user may see/act on something located at $locationId. */
    public function canAccessLocation(?int $locationId): bool
    {
        if (! $this->isSiteScoped()) {
            return true;
        }

        $site = $this->siteLocationId();
        if ($site === null) {
            return true;
        }

        return $locationId !== null && $site === (int) $locationId;
    }

    // ---- Roles & permissions -------------------------------------------
    //
    // `role` (the string column above) stays the primary authorisation input
    // for every route guard that already exists. Custom roles assigned here are
    // additive: effective permissions are the union of the baseline that string
    // grants and every ACTIVE custom role held. A custom role can therefore
    // widen someone's access but never silently narrow it, so switching this
    // feature on cannot lock anyone out of what they can do today.

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /** Assigned custom roles that are currently switched on. */
    public function activeRoles()
    {
        return $this->roles()->where('is_active', true);
    }

    /**
     * ['module' => ['view','create', ...]] — everything this user may do.
     *
     * Computed per request rather than stored, so a permission change takes
     * effect on the user's next request instead of on their next login.
     */
    public function effectivePermissions(): array
    {
        $merged = PermissionRegistry::baselineFor($this->role);

        foreach ($this->activeRoles()->with('permissions')->get() as $role) {
            foreach ($role->grants() as $module => $abilities) {
                $merged[$module] = array_merge($merged[$module] ?? [], $abilities);
            }
        }

        return PermissionRegistry::normalise($merged);
    }

    public function hasPermission(string $module, string $ability = 'view'): bool
    {
        $permissions = $this->effectivePermissions();

        return in_array($ability, $permissions[$module] ?? [], true);
    }

    /**
     * Which UI elements this user should have hidden. `hide` is deliberately
     * NOT part of hasPermission()'s authorisation path — it only ever affects
     * presentation, and is returned separately so the frontend cannot mistake
     * it for an access grant.
     */
    public function hiddenModules(): array
    {
        $hidden = [];

        foreach ($this->effectivePermissions() as $module => $abilities) {
            if (in_array('hide', $abilities, true)) {
                $hidden[] = $module;
            }
        }

        return $hidden;
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('is_read', false);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) :
            'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=128a43&color=fff';
    }
}
