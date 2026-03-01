<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'language',
        'currency',
        'timezone',
        'notification_enabled',
    ];

    protected function casts(): array
    {
        return [
            'notification_enabled' => 'boolean',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
