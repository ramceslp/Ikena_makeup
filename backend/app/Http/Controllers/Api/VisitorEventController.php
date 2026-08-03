<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitorEventRequest;
use App\Models\VisitorEvent;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * VisitorEventController — POST /api/analytics/events (visitor-analytics
 * PR1b, design D4: sdd/visitor-analytics/design).
 *
 * Route middleware: 'auth.optional', public section of routes/api.php
 * (never inside the auth:sanctum group) — a 401 is therefore structurally
 * impossible on this route.
 *
 * NOTE: bot flagging, referrer grouping, entity resolution, and
 * authenticated-user attribution are wired in task 1.8
 * (VisitorEventIngestionTest) — this task (1.7) only proves the request
 * validation boundary.
 */
class VisitorEventController extends Controller
{
    public function store(StoreVisitorEventRequest $request): Response
    {
        $validated = $request->validated();

        $path = Str::of($validated['path'])->before('?')->before('#')->value();

        VisitorEvent::create([
            'event_type' => $validated['event_type'],
            'path' => $path,
            'route_name' => $validated['route_name'] ?? null,
            'entity_type' => $validated['entity_type'] ?? null,
            'entity_id' => null,
            'referrer_group' => 'direct',
            'is_bot' => false,
            'user_id' => null,
            'occurred_at' => now(),
        ]);

        return response()->noContent();
    }
}
