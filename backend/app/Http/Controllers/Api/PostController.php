<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostCardResource;
use App\Http\Resources\PostDetailResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * GET /api/posts — Paginated public post catalog.
     *
     * Accepted query params:
     *   search  — LIKE match against title + excerpt (! ESCAPE)
     *   page    — page number
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::published();

        // Search filter — title or excerpt.
        // Escape LIKE metacharacters using '!' as the ESCAPE char.
        // Portable across MySQL and SQLite (backslash is NOT portable).
        if ($search = $request->query('search')) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $param   = "%{$escaped}%";
            $query->where(function ($q) use ($param) {
                $q->whereRaw("title LIKE ? ESCAPE '!'", [$param])
                  ->orWhereRaw("excerpt LIKE ? ESCAPE '!'", [$param]);
            });
        }

        $query->orderByRaw('COALESCE(published_at, created_at) DESC');

        $posts = $query->paginate(12);

        return response()->json(
            PostCardResource::collection($posts)->response()->getData(true)
        );
    }

    /**
     * GET /api/posts/{slug} — Published post detail by slug.
     * Returns 404 for missing or draft posts.
     */
    public function show(string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)
            ->where('is_published', true)
            ->with(['author', 'images'])
            ->firstOrFail();

        return response()->json([
            'data' => new PostDetailResource($post),
        ]);
    }

    /**
     * GET /api/posts/latest — N most-recent published posts.
     */
    public function latest(Request $request): JsonResponse
    {
        $n = min(max(1, (int) $request->query('n', 3)), 20);

        $posts = Post::published()
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->take($n)
            ->get();

        return response()->json([
            'data' => PostCardResource::collection($posts),
        ]);
    }

    /**
     * GET /api/posts/featured — Most-recent published is_featured post.
     * Falls back to most-recent published post when none is featured.
     * Returns 200 with data:null when zero published posts exist.
     */
    public function featured(): JsonResponse
    {
        // Try featured first
        $post = Post::published()
            ->where('is_featured', true)
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->with(['author', 'images'])
            ->first();

        // Fallback to most-recent published
        if (! $post) {
            $post = Post::published()
                ->orderByRaw('COALESCE(published_at, created_at) DESC')
                ->with(['author', 'images'])
                ->first();
        }

        return response()->json([
            'data' => $post ? new PostDetailResource($post) : null,
        ]);
    }
}
