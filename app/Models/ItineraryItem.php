<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'itinerary_id',
        'place_id',
        'day_number',
        'sort_order',
        'visited_at',
        'duration_minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'visited_at'       => 'datetime',
            'day_number'       => 'integer',
            'sort_order'       => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
