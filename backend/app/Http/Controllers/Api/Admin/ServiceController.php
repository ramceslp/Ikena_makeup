<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Http\Resources\ServiceCardResource;
use App\Http\Resources\ServiceDetailResource;
use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    /**
     * GET /api/admin/services
     * Return all services (published + unpublished), paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->with('category', 'images')
            ->withCount('images')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(
            ServiceCardResource::collection($services)->response()->getData(true)
        );
    }

    /**
     * POST /api/admin/services
     * Create a new service; auto-generate unique slug from title.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['slug'] = $this->uniqueSlug(Str::slug($data['title']));

        $service = Service::create($data);

        $service->load('category', 'images');
        $service->loadCount('images');

        return response()->json([
            'data' => new ServiceDetailResource($service),
        ], 201);
    }

    /**
     * GET /api/admin/services/{service}
     * Return a single service (published or not) by id.
     */
    public function show(Service $service): JsonResponse
    {
        $service->load('category', 'images');
        $service->loadCount('images');

        return response()->json([
            'data' => new ServiceDetailResource($service),
        ]);
    }

    /**
     * POST /api/admin/services/{service}  (multipart; X-HTTP-Method-Override: PATCH or _method=PATCH)
     * Update service fields; regenerate slug when title changes.
     */
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['title']) && $data['title'] !== $service->title) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $service->id);
        }

        $service->update($data);

        $service->load('category', 'images');
        $service->loadCount('images');

        return response()->json([
            'data' => new ServiceDetailResource($service),
        ]);
    }

    /**
     * DELETE /api/admin/services/{service}
     * Delete service; also remove stored image files from disk (cascade removes rows).
     */
    public function destroy(Service $service): JsonResponse
    {
        // Delete stored files before model delete (cascade only removes rows, not files)
        foreach ($service->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $service->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/admin/services/{service}/images
     * Upload multiple images; assign incrementing sort_order from current max.
     */
    public function storeImages(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'images'   => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Enforce per-service 10-image cap across all batches
        $existingCount  = $service->images()->count();
        $incomingCount  = count($request->file('images') ?? []);

        if ($existingCount + $incomingCount > 10) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'images' => [
                    "A service may not have more than 10 images in total. "
                    . "This service already has {$existingCount} image(s); "
                    . "uploading {$incomingCount} more would exceed the limit.",
                ],
            ]);
        }

        $maxSort = $service->images()->max('sort_order') ?? -1;

        $created = [];

        foreach ($request->file('images') as $i => $file) {
            $path  = $file->store('services', 'public');

            $image = ServiceImage::create([
                'service_id' => $service->id,
                'path'       => $path,
                'sort_order' => $maxSort + $i + 1,
            ]);

            $created[] = [
                'id'         => $image->id,
                'url'        => $service->resolveImageUrl($path),
                'sort_order' => $image->sort_order,
            ];
        }

        return response()->json(['data' => $created]);
    }

    /**
     * DELETE /api/admin/services/{service}/images/{image}
     * Delete a single image record and remove the file from disk.
     * Returns 404 if the image does not belong to the given service.
     */
    public function destroyImage(Service $service, ServiceImage $image): JsonResponse
    {
        if ($image->service_id !== $service->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(null, 204);
    }

    /**
     * PATCH /api/admin/services/{service}/images/reorder
     * Accept { order: [imageId, ...] } and reassign sort_order by position.
     */
    public function reorderImages(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $serviceImageIds = $service->images()->pluck('id')->all();
        $submittedIds    = $request->input('order', []);

        // Reject any ID that does not belong to this service (covers foreign and nonexistent IDs)
        foreach ($submittedIds as $imageId) {
            if (! in_array((int) $imageId, $serviceImageIds, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => ["Image ID {$imageId} does not belong to this service."],
                ]);
            }
        }

        foreach ($submittedIds as $position => $imageId) {
            ServiceImage::where('id', $imageId)
                ->where('service_id', $service->id)
                ->update(['sort_order' => $position]);
        }

        // Return the updated ordered image list
        $images = $service->images()->orderBy('sort_order')->get()->map(fn ($img) => [
            'id'         => $img->id,
            'url'        => $service->resolveImageUrl($img->path),
            'sort_order' => $img->sort_order,
        ])->values();

        return response()->json(['data' => $images]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a slug that is unique in the services table.
     * Appends an incrementing suffix when collisions are found.
     *
     * @param  int|null  $excludeId  Service ID to exclude (for updates)
     */
    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug    = $base;
        $counter = 1;

        while (true) {
            $query = Service::where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $slug = "{$base}-{$counter}";
            $counter++;
        }
    }
}
