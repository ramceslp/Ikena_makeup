<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'day_of_week',
        'specific_date',
        'start_time',
        'capacity',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked'    => 'boolean',
            'specific_date' => 'date',
            'capacity'      => 'integer',
            'day_of_week'   => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_blocked', false);
    }
}
