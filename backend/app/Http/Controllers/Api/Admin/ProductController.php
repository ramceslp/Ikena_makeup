<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/admin/products
     * Return all products (published + unpublished), paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('category', 'images')
            ->withCount('images')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(
            ProductCardResource::collection($products)->response()->getData(true)
        );
    }

    /**
     * POST /api/admin/products
     * Create a new product; auto-generate unique slug from title.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['slug'] = $this->uniqueSlug(Str::slug($data['title']));

        $product = Product::create($data);

        $product->load('category', 'images');
        $product->loadCount('images');

        return response()->json([
            'data' => new ProductDetailResource($product),
        ], 201);
    }

    /**
     * GET /api/admin/products/{product}
     * Return a single product (published or not) by id.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load('category', 'images');
        $product->loadCount('images');

        return response()->json([
            'data' => new ProductDetailResource($product),
        ]);
    }

    /**
     * POST /api/admin/products/{product}  (multipart; _method=PATCH)
     * Update product fields; regenerate slug when title changes.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['slug'])) {
            // Manual slug override (spec AM-3): use as-is; uniqueness already validated by UpdateProductRequest.
            // No-op: slug is already present in $data and will be persisted as provided.
        } elseif (isset($data['title']) && $data['title'] !== $product->title) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $product->id);
        }

        $product->update($data);

        $product->load('category', 'images');
        $product->loadCount('images');

        return response()->json([
            'data' => new ProductDetailResource($product),
        ]);
    }

    /**
     * DELETE /api/admin/products/{product}
     * Delete product; remove stored image files from disk (cascade removes rows).
     */
    public function destroy(Product $product): JsonResponse
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $product->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/admin/products/{product}/images
     * Upload multiple images; assign incrementing sort_order from current max.
     */
    public function storeImages(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'images'   => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $existingCount = $product->images()->count();
        $incomingCount = count($request->file('images') ?? []);

        if ($existingCount + $incomingCount > 10) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'images' => [
                    "A product may not have more than 10 images in total. "
                    . "This product already has {$existingCount} image(s); "
                    . "uploading {$incomingCount} more would exceed the limit.",
                ],
            ]);
        }

        $maxSort = $product->images()->max('sort_order') ?? -1;

        $created = [];

        foreach ($request->file('images') as $i => $file) {
            $path  = $file->store('products', 'public');

            $image = ProductImage::create([
                'product_id' => $product->id,
                'path'       => $path,
                'sort_order' => $maxSort + $i + 1,
            ]);

            $created[] = [
                'id'         => $image->id,
                'url'        => $product->resolveImageUrl($path),
                'sort_order' => $image->sort_order,
            ];
        }

        return response()->json(['data' => $created]);
    }

    /**
     * DELETE /api/admin/products/{product}/images/{image}
     * Delete a single image record and remove the file from disk.
     * Returns 404 if the image does not belong to the given product.
     */
    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(null, 204);
    }

    /**
     * PATCH /api/admin/products/{product}/images/reorder
     * Accept { order: [imageId, ...] } and reassign sort_order by position.
     */
    public function reorderImages(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $productImageIds = $product->images()->pluck('id')->all();
        $submittedIds    = $request->input('order', []);

        foreach ($submittedIds as $imageId) {
            if (! in_array((int) $imageId, $productImageIds, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => ["Image ID {$imageId} does not belong to this product."],
                ]);
            }
        }

        DB::transaction(function () use ($submittedIds, $product) {
            foreach ($submittedIds as $position => $imageId) {
                ProductImage::where('id', $imageId)
                    ->where('product_id', $product->id)
                    ->update(['sort_order' => $position]);
            }
        });

        $images = $product->images()->orderBy('sort_order')->get()->map(fn ($img) => [
            'id'         => $img->id,
            'url'        => $product->resolveImageUrl($img->path),
            'sort_order' => $img->sort_order,
        ])->values();

        return response()->json(['data' => $images]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a slug that is unique in the products table.
     * Appends an incrementing suffix when collisions are found.
     *
     * @param  int|null  $excludeId  Product ID to exclude (for updates)
     */
    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug    = $base;
        // Counter starts at 2 intentionally per spec AM-3: first collision yields "master-palette-2".
        $counter = 2;

        while (true) {
            $query = Product::where('slug', $slug);

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
