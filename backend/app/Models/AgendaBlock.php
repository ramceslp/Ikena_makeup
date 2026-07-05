<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AgendaBlock — represents a venue open window with concurrency limits.
 *
 * Recurrence: exactly one of day_of_week or specific_date must be non-null
 * (XOR invariant enforced at the request validation layer).
 *
 * Concurrency: concurrency_limit and soft_threshold are nullable;
 * null values fall back to config('booking.venue.default_concurrency_limit')
 * and config('booking.venue.default_soft_threshold') respectively.
 */
class AgendaBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'specific_date',
        'open_time',
        'close_time',
        'concurrency_limit',
        'soft_threshold',
        'is_blocked',
        'staff_id',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked'        => 'boolean',
            'specific_date'     => 'date',
            'day_of_week'       => 'integer',
            'concurrency_limit' => 'integer',
            'soft_threshold'    => 'integer',
        ];
    }
}
