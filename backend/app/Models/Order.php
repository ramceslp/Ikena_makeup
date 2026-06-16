<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'client_transaction_id',
        'gateway',
        'gateway_transaction_id',
        'amount_cents',
        'currency',
        'status',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'meta'         => 'array',
            'paid_at'      => 'datetime',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // Scopes

    /**
     * Scope to orders that are still pending payment.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
