<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCardResource;
use App\Http\Resources\ServiceDetailResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * GET /api/services — Public catalog with filters and pagination.
     *
     * Accepted query params:
     *   category        — category slug
     *   min_price       — numeric >= 0
     *   max_price       — numeric >= 0
     *   availability_type — immediate|by_appointment
     *   sort            — newest|price_asc|price_desc
     *   search          — LIKE match against title + description
     *   page            — page number
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::published()
            ->with(['category', 'images'])
            ->withCount('images');

        // Search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($cat = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $cat));
        }

        // Price range filters — use filled() so "0" is not silently dropped
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        // Availability filter — only apply for known valid values; invalid values are ignored
        $availability = $request->query('availability_type');
        if (in_array($availability, ['immediate', 'by_appointment'], true)) {
            $query->where('availability_type', $availability);
        }

        // Sorting
        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->orderBy('created_at', 'desc'),
        };

        $services = $query->paginate(12);

        return response()->json(ServiceCardResource::collection($services)->response()->getData(true));
    }

    /**
     * GET /api/services/{slug} — Published service detail with ordered gallery.
     * Returns 404 for missing or unpublished slugs.
     */
    public function show(string $slug): JsonResponse
    {
        $service = Service::where('slug', $slug)
            ->where('is_published', true)
            ->with(['category', 'images'])
            ->firstOrFail();

        return response()->json([
            'data' => new ServiceDetailResource($service),
        ]);
    }
}
