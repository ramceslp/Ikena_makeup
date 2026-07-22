<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CheckoutHandoff — single-use snapshot row backing the mobile-app -> web
 * checkout-handoff flow (mobile-capacitor-setup PR2, design Decision 1).
 *
 * The plaintext token handed to the app is never persisted; only its
 * SHA-256 hash is stored in `token_hash`. `payload` is a JSON snapshot of
 * the cart items (`type = 'product_cart'`) or the booking selection
 * (`type = 'appointment'`) captured at handoff time — redeem re-validates
 * it against live state via CartCheckoutAction / CreateBookingAction rather
 * than trusting it verbatim.
 */
class CheckoutHandoff extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'token_hash',
        'payload',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: look up a row by its plaintext token — hashes the given
     * plaintext and matches against the stored `token_hash`. Callers never
     * query `token_hash` directly so the hashing algorithm stays centralized
     * here.
     */
    public function scopeByToken(Builder $query, string $plaintextToken): Builder
    {
        return $query->where('token_hash', hash('sha256', $plaintextToken));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
