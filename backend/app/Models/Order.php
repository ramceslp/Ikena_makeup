<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use DomainException;
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
        'appointment_id',
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

    // -------------------------------------------------------------------------
    // Boot — XOR guard
    // -------------------------------------------------------------------------

    /**
     * Enforce that exactly one of course_id / appointment_id is non-null.
     * An order must belong to either a course OR an appointment, never both
     * and never neither.
     *
     * This fires on every create and update via the `saving` event.
     */
    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            $hasCourse      = ! is_null($order->course_id);
            $hasAppointment = ! is_null($order->appointment_id);

            if ($hasCourse === $hasAppointment) {
                throw new DomainException(
                    'An order must have exactly one of course_id or appointment_id set (XOR). ' .
                    "Got course_id={$order->course_id}, appointment_id={$order->appointment_id}."
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope to orders that are still pending payment.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
