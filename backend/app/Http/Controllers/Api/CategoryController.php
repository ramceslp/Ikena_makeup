<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * GET /api/categories — Public list of all categories ordered by name.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get(['id', 'name', 'slug']);

        return response()->json([
            'data' => $categories,
        ]);
    }
}
