<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePushNotificationRequest;
use App\Http\Resources\PushNotificationLogResource;
use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Services\Push\PushDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PushNotificationController — the admin's notification centre
 * (push-notifications Slice 3, decision D3: sdd/push-notifications/HANDOFF.md).
 *
 * index() serves the unified history: automatic triggers (a news post
 * published, a course made available) AND custom admin sends, newest first.
 * store() composes and queues a custom broadcast.
 */
class PushNotificationController extends Controller
{
    /**
     * GET /api/admin/push-notifications
     *
     * Optional `?type=` filter (post.published | course.published | custom).
     * `sender` is eager loaded to keep the listing free of an N+1 across the
     * page — most rows are automatic and have no sender at all.
     */
    public function index(Request $request): JsonResponse
    {
        $logs = PushNotificationLog::query()
            ->with('sender')
            ->when(
                $request->filled('type'),
                fn ($query) => $query->where('type', $request->string('type')),
            )
            ->latestFirst()
            ->paginate(20);

        return response()->json(
            PushNotificationLogResource::collection($logs)->response()->getData(true)
        );
    }

    /**
     * POST /api/admin/push-notifications
     *
     * Returns 201 with the newly created history row.
     *
     * On a real queue connection the row comes back still 'pending' — success
     * and failure counts are filled in by App\Jobs\BroadcastPushNotification
     * once FCM answers, and the admin sees them by refreshing the history.
     *
     * The refresh() below matters when QUEUE_CONNECTION is `sync` (the current
     * setting in this project's .env), where dispatch() runs the job inline
     * before this method returns. The job loads and updates its OWN model
     * instance, so without the refresh this response would report 'pending'
     * while the database already says 'sent' — an API contradicting its own
     * stored state. It is a cheap no-op on an async connection.
     *
     * A row whose status comes back 'skipped' means config('push.enabled') is
     * false — Firebase is not configured yet (HANDOFF §8). That is surfaced
     * rather than hidden, so the admin is never left believing a broadcast went
     * out when it did not.
     */
    public function store(StorePushNotificationRequest $request, PushDispatcher $dispatcher): JsonResponse
    {
        $validated = $request->validated();

        $log = $dispatcher->custom(
            title: $validated['title'],
            body: $validated['body'],
            route: $validated['route'] ?? null,
            sentBy: $request->user()->id,
        );

        $log->refresh()->load('sender');

        return response()->json([
            'data' => new PushNotificationLogResource($log),
        ], 201);
    }

    /**
     * GET /api/admin/push-notifications/stats
     *
     * How many devices a broadcast would currently reach. Shown next to the
     * compose form so the admin knows the size of the audience before sending
     * — "send to everyone" is otherwise an unnervingly opaque button.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'device_count'  => DeviceToken::query()->count(),
                'push_enabled'  => (bool) config('push.enabled'),
            ],
        ]);
    }
}
