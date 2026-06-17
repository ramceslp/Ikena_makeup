<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSlotRequest;
use App\Http\Requests\Admin\UpdateSlotRequest;
use App\Http\Resources\SlotResource;
use App\Models\Service;
use App\Models\ServiceSlot;
use Illuminate\Http\JsonResponse;

class ServiceSlotController extends Controller
{
    /**
     * GET /api/admin/services/{service}/slots
     * List all slots for a service (admin only).
     */
    public function index(Service $service): JsonResponse
    {
        $slots = $service->slots()->orderBy('day_of_week')->orderBy('specific_date')->get();

        return response()->json([
            'data' => SlotResource::collection($slots),
        ]);
    }

    /**
     * POST /api/admin/services/{service}/slots
     * Create a new slot for a service.
     */
    public function store(StoreSlotRequest $request, Service $service): JsonResponse
    {
        $data               = $request->validated();
        $data['service_id'] = $service->id;

        $slot = ServiceSlot::create($data);

        return response()->json([
            'data' => new SlotResource($slot),
        ], 201);
    }

    /**
     * PATCH /api/admin/services/{service}/slots/{slot}
     * Update a slot (partial update allowed).
     */
    public function update(UpdateSlotRequest $request, Service $service, ServiceSlot $slot): JsonResponse
    {
        abort_if($slot->service_id !== $service->id, 404);

        $slot->update($request->validated());

        return response()->json([
            'data' => new SlotResource($slot->fresh()),
        ]);
    }

    /**
     * DELETE /api/admin/services/{service}/slots/{slot}
     * Delete a slot.
     */
    public function destroy(Service $service, ServiceSlot $slot): JsonResponse
    {
        abort_if($slot->service_id !== $service->id, 404);

        $slot->delete();

        return response()->json(null, 204);
    }
}
