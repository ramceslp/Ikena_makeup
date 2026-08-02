<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    use HasFactory;

    /**
     * The documented spelling for `appointments.status`. Note this is a
     * DIFFERENT enum from `Order::STATUSES` — 'cancelled' (two Ls) is correct
     * HERE, while `orders.status` uses 'canceled' (single L). See the
     * docblock on `Order::STATUSES` for why the two legitimately disagree.
     */
    public const STATUSES = ['pending', 'confirmed', 'paid', 'cancelled'];

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
        'service_price_cents',      // D1: price snapshot taken at booking, never recomputed
        'deposit_collected_cents',  // D1: money the GATEWAY actually captured
        'deposit_collected_at',
        'settled_amount_cents',     // D1: money collected IN PERSON at settlement
        'settled_at',
        'status',
        'cancelled_by_id',
        'cancelled_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date'           => 'date',
            'cancelled_at'             => 'datetime',
            'reminder_sent_at'         => 'datetime',
            'deposit_amount_cents'     => 'integer',
            'service_price_cents'      => 'integer',
            'deposit_collected_cents'  => 'integer',
            'deposit_collected_at'     => 'datetime',
            'settled_amount_cents'     => 'integer',
            'settled_at'               => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Boot — status spelling guard (D5) + money invariant guard (D2)
    // -------------------------------------------------------------------------

    /**
     * Mirrors `Order::booted()`'s style: a single `saving` hook enforces every
     * write-time invariant so no call site can bypass it by updating instead
     * of creating.
     *
     * Money-invariant rules (design D2 — these are the anti-double-count guard):
     *  1. Pairing — `settled_at`/`settled_amount_cents` are both NULL or both
     *     set; `deposit_collected_at` is NULL iff `deposit_collected_cents`
     *     is 0. A half-recorded money event can never exist.
     *  2. Write-once — once `settled_at` (or `deposit_collected_at`) is
     *     persisted, that pair becomes immutable. Recorded money can never be
     *     re-recorded, which is what makes double-counting structurally
     *     impossible: there is no code path that can write the same money
     *     event twice.
     *
     * `service_price_cents` is intentionally NOT enforced as "required on
     * create" here — see the migration docblock
     * (2026_08_01_100000_add_settlement_columns_to_appointments.php) and the
     * PR1a apply-progress for why: `CreateBookingAction` (PR1b scope) does not
     * yet supply this snapshot, and this guard must not break that write path
     * before PR1b lands. The DB-level NOT NULL constraint plus a bridging
     * DEFAULT 0 covers the schema-integrity concern in the interim.
     */
    protected static function booted(): void
    {
        static::saving(function (Appointment $appointment) {
            static::assertKnownStatus($appointment);
            static::assertDepositPairing($appointment);
            static::assertDepositImmutable($appointment);
            static::assertSettlementPairing($appointment);
            static::assertSettlementImmutable($appointment);
        });
    }

    private static function assertKnownStatus(Appointment $appointment): void
    {
        if (! in_array($appointment->status, self::STATUSES, true)) {
            throw new DomainException(
                "Unknown appointment status '{$appointment->status}'. Must be one of: " .
                implode(', ', self::STATUSES) . '. ' .
                "Note: orders use a different status enum ('canceled', single L) — " .
                'see Order::STATUSES.'
            );
        }
    }

    private static function assertDepositPairing(Appointment $appointment): void
    {
        $hasTimestamp = ! is_null($appointment->deposit_collected_at);
        $hasAmount    = ((int) $appointment->deposit_collected_cents) !== 0;

        if ($hasTimestamp !== $hasAmount) {
            throw new DomainException(
                'deposit_collected_at and deposit_collected_cents must be set together: ' .
                "got deposit_collected_at={$appointment->deposit_collected_at}, " .
                "deposit_collected_cents={$appointment->deposit_collected_cents}."
            );
        }
    }

    private static function assertDepositImmutable(Appointment $appointment): void
    {
        if (! $appointment->exists) {
            return;
        }

        $recordedAt = $appointment->getOriginal('deposit_collected_at');

        if (! is_null($recordedAt) && $appointment->isDirty(['deposit_collected_at', 'deposit_collected_cents'])) {
            throw new DomainException(
                'deposit_collected_at/deposit_collected_cents are write-once: this deposit was ' .
                "already recorded at {$recordedAt} and cannot be mutated."
            );
        }
    }

    private static function assertSettlementPairing(Appointment $appointment): void
    {
        $hasTimestamp = ! is_null($appointment->settled_at);
        $hasAmount    = ! is_null($appointment->settled_amount_cents);

        if ($hasTimestamp !== $hasAmount) {
            throw new DomainException(
                'settled_at and settled_amount_cents must be set together (both NULL or both set): ' .
                "got settled_at={$appointment->settled_at}, " .
                "settled_amount_cents={$appointment->settled_amount_cents}."
            );
        }
    }

    private static function assertSettlementImmutable(Appointment $appointment): void
    {
        if (! $appointment->exists) {
            return;
        }

        $recordedAt = $appointment->getOriginal('settled_at');

        if (! is_null($recordedAt) && $appointment->isDirty(['settled_at', 'settled_amount_cents'])) {
            throw new DomainException(
                'settled_at/settled_amount_cents are write-once: this appointment was already ' .
                "settled at {$recordedAt} and cannot be re-settled or mutated."
            );
        }
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
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Portable "ended before $moment" predicate (design D6), used by the
     * receivable-bucket queries (PR4a) to find appointments whose scheduled
     * end has passed a given cutoff (typically `now()->subDay()` for the
     * 24h grace period).
     *
     * Combining a DATE column and a TIME column into one instant is
     * driver-specific in SQL (`TIMESTAMP()` on MySQL vs `datetime(date||time)`
     * on SQLite). This predicate avoids that entirely with a portable
     * two-branch OR, built from $moment split into its date and time parts
     * in PHP (Carbon) before the query is built:
     *
     *   scheduled_date < M.date
     *   OR (scheduled_date = M.date AND scheduled_end_time <= M.time)
     *
     * `scheduled_end_time` is a NULLABLE `time` column
     * (2026_06_27_100001_add_scheduled_end_time_to_appointments.php:13).
     * SQL's `NULL <= X` evaluates to NULL (falsy in WHERE), so the second
     * branch never matches a same-day row with a NULL end time — it is only
     * caught the FOLLOWING day once the first branch fires on the date
     * comparison alone (adjustment #4,
     * architecture/admin-reports-design-adjustments). This is intentional,
     * not a gap: a same-day appointment cannot be "24h overdue" yet anyway.
     *
     * `status != 'cancelled'` is folded into this scope rather than repeated
     * by every caller, because every current and planned caller (receivable
     * buckets B/C) always pairs the two conditions — see design D6's bucket
     * table.
     *
     * Uses `whereDate()` rather than a raw string-equality `where()` on
     * `scheduled_date`: the column is cast to `'date'` on the model, and
     * Eloquent serializes `date`-cast attributes with the connection's full
     * date FORMAT (`Y-m-d H:i:s`) on write, not a bare `Y-m-d` string — a raw
     * `where('scheduled_date', '=', $date)` against a plain `Y-m-d` value
     * would silently never match. `whereDate()` is Laravel's own portable
     * abstraction for exactly this comparison (already used the same way in
     * `AppointmentController::index()`), so this stays free of hand-written
     * driver-specific SQL per design D3.
     */
    public function scopeEndedBefore(Builder $query, Carbon $moment): Builder
    {
        $date = $moment->format('Y-m-d');
        $time = $moment->format('H:i:s');

        return $query
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q) use ($date, $time) {
                $q->whereDate('scheduled_date', '<', $date)
                    ->orWhere(function (Builder $q2) use ($date, $time) {
                        $q2->whereDate('scheduled_date', '=', $date)
                            ->where('scheduled_end_time', '<=', $time);
                    });
            });
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
