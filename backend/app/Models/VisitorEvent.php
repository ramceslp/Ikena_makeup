<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Scope: rows that should count toward reported figures — excludes bot
     * traffic and staff (admin/instructor) browsing.
     *
     * The nested closure is NOT stylistic. SQL's `NOT IN` evaluates to NULL
     * for a NULL `user_id`, and SQL discards NULL rows in a WHERE clause —
     * a bare `whereNotIn('user_id', $staffIds)` would silently drop every
     * anonymous event, which is most of this site's traffic. The explicit
     * `whereNull('user_id')` branch keeps anonymous rows regardless of the
     * staff exclusion.
     */
    public function scopeReportable(Builder $q): Builder
    {
        return $q->where('is_bot', false)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('user_id')
                ->orWhereNotIn('user_id', static::staffIds()));
    }

    /**
     * @return array<int, int>
     */
    protected static function staffIds(): array
    {
        return User::query()->whereIn('role', ['admin', 'instructor'])->pluck('id')->all();
    }
}
