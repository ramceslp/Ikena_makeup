<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PushNotificationLog — the audit trail behind the admin "notification history"
 * screen (push-notifications Slice 1, decision D3:
 * sdd/push-notifications/HANDOFF.md).
 *
 * One row per broadcast attempt. Written by App\Services\Push\PushDispatcher
 * before the send is queued (status 'pending', or 'skipped' when
 * config('push.enabled') is false), then completed by
 * App\Jobs\BroadcastPushNotification with the per-token outcome FCM reported.
 */
class PushNotificationLog extends Model
{
    use HasFactory;

    public const TYPE_POST_PUBLISHED = 'post.published';

    public const TYPE_COURSE_PUBLISHED = 'course.published';

    public const TYPE_CUSTOM = 'custom';

    /** Queued, not yet processed by the broadcast job. */
    public const STATUS_PENDING = 'pending';

    /** FCM accepted the send; see success_count / failure_count for per-token detail. */
    public const STATUS_SENT = 'sent';

    /** The send threw — nothing was delivered. */
    public const STATUS_FAILED = 'failed';

    /** config('push.enabled') was false, so no send was attempted at all. */
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'type',
        'title',
        'body',
        'data',
        'audience',
        'sent_by',
        'recipients_count',
        'success_count',
        'failure_count',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'recipients_count' => 'integer',
            'success_count' => 'integer',
            'failure_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** The admin who composed a custom send; null for automatic triggers. */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** Newest first — the only order the admin history is ever read in. */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
