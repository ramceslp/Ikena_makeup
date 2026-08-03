<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * VisitorEvent — an append-only, identity-free navigation event row
 * (visitor-analytics PR1, design D1/D2: sdd/visitor-analytics/design).
 *
 * No visitor identity of any kind is stored. `user_id` is the only
 * identity column and is populated only when the request already carried
 * a bearer token.
 *
 * `$timestamps = false` — the first model in this codebase to set this.
 * `created_at` would be provably identical to `occurred_at` on every row
 * (both are `now()` computed inside the same synchronous request), and
 * `updated_at` is meaningless on a table with no update path. If the write
 * is ever made asynchronous, `created_at` returns in the same migration
 * that introduces the queue — only then can the two values differ.
 */
class VisitorEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'path',
        'route_name',
        'entity_type',
        'entity_id',
        'referrer_group',
        'is_bot',
        'user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
