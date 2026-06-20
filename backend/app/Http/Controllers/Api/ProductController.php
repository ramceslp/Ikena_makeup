<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products — Public catalog with filters and pagination.
     *
     * Accepted query params:
     *   search       — LIKE match against title + description
     *   category     — category slug
     *   min_price    — numeric >= 0
     *   max_price    — numeric >= 0
     *   stock_state  — in_stock (stock_qty > 0) | out_of_stock (stock_qty = 0)
     *   sort         — newest | price_asc | price_desc
     *   page         — page number
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::published()
            ->with(['category', 'images'])
            ->withCount('images');

        // Search filter — title or description
        // Escape LIKE special characters so user input is treated as literals.
        if ($search = $request->query('search')) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escaped) {
                $q->where('title', 'like', "%{$escaped}%")
                  ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        // Category filter — category slug
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

        // Stock state filter (PC-2: in_stock / out_of_stock are API params, not labels)
        $stockFilter = $request->query('stock_state');
        if ($stockFilter === 'in_stock') {
            $query->where('stock_qty', '>', 0);
        } elseif ($stockFilter === 'out_of_stock') {
            $query->where('stock_qty', '=', 0);
        }

        // Sorting
        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate(12);

        return response()->json(ProductCardResource::collection($products)->response()->getData(true));
    }

    /**
     * GET /api/products/{slug} — Published product detail with ordered gallery.
     * Returns 404 for missing or unpublished slugs.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->with(['category', 'images'])
            ->firstOrFail();

        return response()->json([
            'data' => new ProductDetailResource($product),
        ]);
    }
}
