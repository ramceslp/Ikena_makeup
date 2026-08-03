<?php

namespace App\Http\Controllers\Api;

use App\Analytics\BotDetector;
use App\Analytics\EntityResolver;
use App\Analytics\ReferrerGrouper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitorEventRequest;
use App\Models\VisitorEvent;
use Illuminate\Http\Response;

/**
 * VisitorEventController — POST /api/analytics/events (visitor-analytics
 * PR1b, design D4: sdd/visitor-analytics/design).
 *
 * Route middleware: 'auth.optional', public section of routes/api.php
 * (never inside the auth:sanctum group) — a 401 is therefore structurally
 * impossible on this route.
 *
 * The write is SYNCHRONOUS, never queued (see
 * tests/Feature/Analytics/VisitorEventSyncWriteTest.php). config/queue.php
 * defaults to the 'database' driver with no worker running anywhere in this
 * project; dispatching this insert to a job would pass every test
 * (phpunit.xml/phpunit.mysql.xml both force QUEUE_CONNECTION=sync) while
 * silently losing every event in production.
 *
 * Every derived field — is_bot, referrer_group, entity_id, occurred_at,
 * user_id — is computed here, server-side, and never taken from the
 * request body (StoreVisitorEventRequest deliberately has no rule for any
 * of them). The raw User-Agent and raw referrer are used transiently by
 * BotDetector/ReferrerGrouper and never stored; the raw client IP is not
 * read anywhere in this controller at all.
 */
class VisitorEventController extends Controller
{
    public function __construct(
        private readonly BotDetector $botDetector,
        private readonly ReferrerGrouper $referrerGrouper,
        private readonly EntityResolver $entityResolver,
    ) {
    }

    public function store(StoreVisitorEventRequest $request): Response
    {
        $validated = $request->validated();

        // 'path' arrives already normalized (query string / fragment
        // stripped) — see StoreVisitorEventRequest::prepareForValidation(),
        // which runs BEFORE the max:255 rule so the length check applies
        // to the value that is actually stored.
        VisitorEvent::create([
            'event_type' => $validated['event_type'],
            'path' => $validated['path'],
            'route_name' => $validated['route_name'] ?? null,
            'entity_type' => $validated['entity_type'] ?? null,
            'entity_id' => $this->entityResolver->resolve(
                $validated['entity_type'] ?? null,
                $validated['entity_slug'] ?? null,
            ),
            'referrer_group' => $this->referrerGrouper->group($validated['referrer'] ?? null),
            'is_bot' => $this->botDetector->isBot($request->userAgent()),
            'user_id' => $request->user()?->id,
            'occurred_at' => now(),
        ]);

        return response()->noContent();
    }
}
