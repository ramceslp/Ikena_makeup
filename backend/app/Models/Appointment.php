<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'user_id',
        'order_id',
        'scheduled_date',
        'scheduled_time',
        'slot_key',
        'whatsapp',
        'payment_mode',
        'deposit_amount_cents',
        'status',
        'cancelled_by_id',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date'      => 'date',
            'cancelled_at'        => 'datetime',
            'deposit_amount_cents' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the slot_key string that uniquely identifies a booking slot.
     * Format: "{service_id}|{date}|{time}"
     * Set on appointment creation; nulled on cancellation to free the slot.
     */
    public static function makeSlotKey(int $serviceId, string $date, string $time): string
    {
        return "{$serviceId}|{$date}|{$time}";
    }
}
