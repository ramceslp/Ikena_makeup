<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'appointment_id',
        'type',
        'client_transaction_id',
        'gateway',
        'gateway_transaction_id',
        'amount_cents',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'status',
        'paid_at',
        'reserved_until',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents'   => 'integer',
            'subtotal_cents' => 'integer',
            'tax_cents'      => 'integer',
            'total_cents'    => 'integer',
            'meta'           => 'array',
            'paid_at'        => 'datetime',
            'reserved_until' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Boot — type-discriminated invariant (replaces XOR guard)
    // -------------------------------------------------------------------------

    /**
     * Type-aware invariant. Fires on every create and update via the `saving` event.
     *
     * The `creating` hook (below) infers `type` when it is not explicitly set,
     * so existing call sites that omit `type` continue to work unchanged.
     *
     * Rules per type:
     *  course       → course_id NOT NULL,  appointment_id NULL
     *  appointment  → appointment_id NOT NULL, course_id NULL
     *  product_cart → course_id NULL, appointment_id NULL (items validated at controller)
     *  default      → unknown type → DomainException
     */
    protected static function booted(): void
    {
        // saving hook fires before creating for new models.
        // It serves double duty:
        //  1. Infer type when not explicitly set (regression-safe creating hook behaviour).
        //  2. Enforce the type-dispatched shape invariant.
        static::saving(function (Order $order) {
            // Step 1: infer type for new records when caller omitted it.
            // This is a regression-safe hook: existing call sites (CheckoutController,
            // BookingController, AppointmentAdminTest, etc.) do NOT pass `type`,
            // so we infer it from the FK pattern they DO provide.
            if ($order->isClean('type') || empty($order->type)) {
                if (! is_null($order->appointment_id)) {
                    $order->type = 'appointment';
                } else {
                    // Defaults to 'course' — matches the column default and the
                    // historical behavior when course_id is present.
                    $order->type = 'course';
                }
            }

            // Step 2: enforce type-dispatched shape invariant.
            $hasCourse      = ! is_null($order->course_id);
            $hasAppointment = ! is_null($order->appointment_id);

            match ($order->type) {
                'course'       => static::assertCourseShape($order, $hasCourse, $hasAppointment),
                'appointment'  => static::assertAppointmentShape($order, $hasCourse, $hasAppointment),
                'product_cart' => static::assertProductCartShape($order, $hasCourse, $hasAppointment),
                default        => throw new DomainException(
                    "Unknown order type '{$order->type}'."
                ),
            };
        });
    }

    private static function assertCourseShape(Order $order, bool $hasCourse, bool $hasAppointment): void
    {
        if (! $hasCourse || $hasAppointment) {
            throw new DomainException(
                "Order type='course' requires course_id NOT NULL and appointment_id NULL. " .
                "Got course_id={$order->course_id}, appointment_id={$order->appointment_id}."
            );
        }
    }

    private static function assertAppointmentShape(Order $order, bool $hasCourse, bool $hasAppointment): void
    {
        if (! $hasAppointment || $hasCourse) {
            throw new DomainException(
                "Order type='appointment' requires appointment_id NOT NULL and course_id NULL. " .
                "Got course_id={$order->course_id}, appointment_id={$order->appointment_id}."
            );
        }
    }

    private static function assertProductCartShape(Order $order, bool $hasCourse, bool $hasAppointment): void
    {
        if ($hasCourse || $hasAppointment) {
            throw new DomainException(
                "Order type='product_cart' requires both course_id and appointment_id to be NULL. " .
                "Got course_id={$order->course_id}, appointment_id={$order->appointment_id}."
            );
        }
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
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

    /**
     * Scope to expired pending product_cart orders.
     * Used by the ReleaseExpiredReservations command.
     */
    public function scopeExpiredProductCarts(Builder $query): Builder
    {
        return $query
            ->where('type', 'product_cart')
            ->where('status', 'pending')
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now());
    }
}
