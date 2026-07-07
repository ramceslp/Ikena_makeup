<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgendaBlockRequest;
use App\Http\Requests\Admin\UpdateAgendaBlockRequest;
use App\Http\Resources\AgendaBlockResource;
use App\Models\AgendaBlock;
use Illuminate\Http\JsonResponse;

class AgendaBlockController extends Controller
{
    /**
     * GET /api/admin/agenda
     * List all venue agenda blocks (admin only).
     */
    public function index(): JsonResponse
    {
        $blocks = AgendaBlock::orderBy('day_of_week')->orderBy('specific_date')->orderBy('open_time')->get();

        return response()->json([
            'data' => AgendaBlockResource::collection($blocks),
        ]);
    }

    /**
     * GET /api/admin/agenda/{block}
     * Show a single agenda block.
     */
    public function show(AgendaBlock $block): JsonResponse
    {
        return response()->json([
            'data' => new AgendaBlockResource($block),
        ]);
    }

    /**
     * POST /api/admin/agenda
     * Create a new agenda block.
     */
    public function store(StoreAgendaBlockRequest $request): JsonResponse
    {
        $block = AgendaBlock::create($request->validated());

        return response()->json([
            'data' => new AgendaBlockResource($block),
        ], 201);
    }

    /**
     * PATCH /api/admin/agenda/{block}
     * Update an agenda block (partial update allowed).
     */
    public function update(UpdateAgendaBlockRequest $request, AgendaBlock $block): JsonResponse
    {
        $block->update($request->validated());

        return response()->json([
            'data' => new AgendaBlockResource($block->fresh()),
        ]);
    }

    /**
     * DELETE /api/admin/agenda/{block}
     * Delete an agenda block.
     */
    public function destroy(AgendaBlock $block): JsonResponse
    {
        $block->delete();

        return response()->json(null, 204);
    }
}
