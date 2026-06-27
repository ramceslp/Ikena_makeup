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
        'scheduled_end_time',  // DM-001: denormalized end time for overlap queries (added Slice 1)
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
     * Format: "{service_id}|{date}|{HH:MM}"
     *
     * Time is normalized to H:i (HH:MM) so that MySQL TIME columns ('10:00:00')
     * and SQLite TIME values ('10:00') produce the same key. Normalization
     * happens here — callers must NOT truncate or pad the time themselves.
     *
     * Set on appointment creation; nulled on cancellation to free the slot.
     */
    public static function makeSlotKey(int $serviceId, string $date, string $time): string
    {
        // Normalize to HH:MM regardless of whether the DB driver returned HH:MM or HH:MM:SS.
        $time = substr($time, 0, 5);

        return "{$serviceId}|{$date}|{$time}";
    }
}
