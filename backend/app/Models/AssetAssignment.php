<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AssetAssignment extends Model
{
    protected $table = 'asset_assignments';
    
    protected $fillable = [
        'asset_id',
        'assigned_to_type',
        'assigned_to_id',
        'location_id',
        'quantity',
        'assigned_date',
        'due_date',        // ADD THIS
        'status',
        'image_path'
    ];

    protected $appends = ['image_url', 'recipient_name'];

    protected $casts = [
        'assigned_date' => 'datetime',
        'due_date' => 'datetime',  // ADD THIS
    ];

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    // Polymorphic relationship for assigned_to (staff OR program)
    public function assignee(): MorphTo
    {
        return $this->morphTo('assigned_to');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function returns()
    {
        return $this->hasMany(AssetReturn::class, 'assignment_id');
    }

    // Get assignee photo URL (staff photo or program placeholder)
    public function getAssigneePhotoAttribute(): ?string
    {
        if ($this->assigned_to_type === 'staff') {
            $staff = \App\Models\Staff::find($this->assigned_to_id);
            return $staff?->photo_path_url;
        }
        return null;
    }

    // Helper attribute to get recipient name
    public function getRecipientNameAttribute(): string
    {
        if ($this->assigned_to_type === 'staff') {
            return Staff::find($this->assigned_to_id)?->full_name ?? 'Unknown Staff';
        }
        if ($this->assigned_to_type === 'program') {
            return Program::find($this->assigned_to_id)?->name ?? 'Unknown Program';
        }
        return 'N/A';
    }

    // Alias for assignee name (used in blade)
    public function getStaffAttribute(): ?string
    {
        return $this->recipient_name;
    }

    // Get assignee name (used in blade)
    public function getAssigneeNameAttribute(): string
    {
        if ($this->assigned_to_type === 'staff') {
            return \App\Models\Staff::find($this->assigned_to_id)?->full_name ?? 'Unknown Staff';
        }
        if ($this->assigned_to_type === 'program') {
            return \App\Models\Program::find($this->assigned_to_id)?->name ?? 'Unknown Program';
        }
        return 'N/A';
    }

    // Get program name if assigned to program
    public function getProgramAttribute(): ?string
    {
        if ($this->assigned_to_type === 'program') {
            return Program::find($this->assigned_to_id)?->name;
        }
        return null;
    }

    // Scope for active assignments
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // Scope for overdue assignments
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->whereNotNull('due_date')
                     ->where('due_date', '<', now());
    }

    // Scope for returned assignments
    public function scopeReturned(Builder $query): Builder
    {
        return $query->where('status', 'returned');
    }

    // Check if assignment is overdue
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'active' 
            && $this->due_date 
            && $this->due_date < now();
    }

    // Get status with proper badge class
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'active'   => 'bg-brand-50 text-brand',
            'returned' => 'bg-slate-100 text-slate-500',
            'overdue'  => 'bg-red-50 text-red-500',
            'assigned' => 'bg-blue-50 text-blue-700',
            default    => 'bg-slate-100 text-slate-600',
        };
    }
}