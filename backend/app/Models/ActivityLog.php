<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\InAppNotifier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    // Allow these fields to be filled via ActivityLog::create()
    protected $fillable = [
        'user_id',
        'action',
        'description'];

    /**
     * Log the action and also notify the admins (and the actor) in-app with the
     * same description — see InAppNotifier for who receives it and why the
     * workflow modules don't use this.
     */
    public static function createAndNotify(array $attributes, ?string $url = null): self
    {
        $log = static::create($attributes);

        InAppNotifier::admins(
            $attributes['description'],
            'activity_'.Str::snake(Str::lower($attributes['action'])),
            $url,
            $attributes['user_id'] ?? null,
        );

        return $log;
    }

    /**
     * Get the user that owns the activity log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
