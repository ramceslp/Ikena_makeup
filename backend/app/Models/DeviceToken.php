<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeviceToken — a registered FCM registration token for push notifications
 * (mobile-capacitor-setup PR3, design Decision 2).
 *
 * Registered via `POST /api/device-tokens` (idempotent upsert on `token`),
 * removed via `DELETE /api/device-tokens` (logout / permission revoke), and
 * deleted automatically when a send fails with FCM's
 * NotRegistered/InvalidToken conditions (see
 * App\Listeners\InvalidateFcmDeviceToken).
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
