<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePostRequest;
use App\Http\Requests\Admin\UpdatePostRequest;
use App\Http\Resources\PostCardResource;
use App\Http\Resources\PostDetailResource;
use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class PostController extends Controller
{
    /**
     * GET /api/admin/posts
     * Return all posts (published + drafts), paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(
            PostCardResource::collection($posts)->response()->getData(true)
        );
    }

    /**
     * POST /api/admin/posts
     * Create a new post; auto-generate unique slug from title; sanitize body.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Slug: use submitted or generate from title
        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']));
        }

        // Sanitize body HTML server-side
        $data['body'] = $this->cleanBody($data['body']);

        // Set author to current admin
        $data['author_id'] = $request->user()->id;

        // Remove cover_image from fillable data (handled separately)
        unset($data['cover_image']);

        // Auto-set published_at on first publish
        if (! empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $post = Post::create($data);

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $path                   = $request->file('cover_image')->store('posts/covers', 'public');
            $post->cover_image_path = $path;
            $post->save();
        }

        $post->load('author', 'images');

        return response()->json([
            'data' => new PostDetailResource($post),
        ], 201);
    }

    /**
     * GET /api/admin/posts/{post}
     * Return a single post (published or draft) by id.
     */
    public function show(Post $post): JsonResponse
    {
        $post->load('author', 'images');

        return response()->json([
            'data' => new PostDetailResource($post),
        ]);
    }

    /**
     * POST /api/admin/posts/{post}  (multipart; _method=PATCH)
     * Update post fields; handle slug override; re-sanitize body; auto-set published_at.
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $data = $request->validated();

        // Slug logic: manual override → keep as-is; no slug provided + title changed → regenerate
        if (! empty($data['slug'])) {
            // Manual override — already unique-validated by UpdatePostRequest
        } elseif (isset($data['title']) && $data['title'] !== $post->title) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $post->id);
        }

        // Re-sanitize body if provided
        if (isset($data['body'])) {
            $data['body'] = $this->cleanBody($data['body']);
        }

        // Remove cover_image from fillable data
        unset($data['cover_image']);

        // Auto-set published_at on first publish (only when it was null before)
        if (! empty($data['is_published']) && is_null($post->published_at) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $post->update($data);

        // Handle cover image upload if provided
        if ($request->hasFile('cover_image')) {
            // Remove old cover
            if ($post->cover_image_path) {
                Storage::disk('public')->delete($post->cover_image_path);
            }
            $path                   = $request->file('cover_image')->store('posts/covers', 'public');
            $post->cover_image_path = $path;
            $post->save();
        }

        $post->load('author', 'images');

        return response()->json([
            'data' => new PostDetailResource($post),
        ]);
    }

    /**
     * DELETE /api/admin/posts/{post}
     * Delete post; remove cover and body image files from disk (cascade removes rows).
     */
    public function destroy(Post $post): JsonResponse
    {
        // Delete cover image file
        if ($post->cover_image_path) {
            Storage::disk('public')->delete($post->cover_image_path);
        }

        // Delete all body image files
        foreach ($post->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $post->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/admin/posts/{post}/cover
     * Upload or replace the cover image.
     */
    public function storeCoverImage(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'cover_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        // Remove old cover if present
        if ($post->cover_image_path) {
            Storage::disk('public')->delete($post->cover_image_path);
        }

        $path                   = $request->file('cover_image')->store('posts/covers', 'public');
        $post->cover_image_path = $path;
        $post->save();

        return response()->json([
            'data' => [
                'cover_image_url' => $post->cover_image_url,
                'cover_image_path' => $post->cover_image_path,
            ],
        ]);
    }

    /**
     * DELETE /api/admin/posts/{post}/cover
     * Remove the cover image.
     */
    public function destroyCoverImage(Post $post): JsonResponse
    {
        if ($post->cover_image_path) {
            Storage::disk('public')->delete($post->cover_image_path);
            $post->cover_image_path = null;
            $post->save();
        }

        return response()->json([
            'data' => ['cover_image_url' => null],
        ]);
    }

    /**
     * POST /api/admin/posts/{post}/images
     * Upload one or more body images; assign incrementing sort_order.
     */
    public function storeImages(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'images'   => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $existingCount = $post->images()->count();
        $incomingCount = count($request->file('images') ?? []);

        if ($existingCount + $incomingCount > 10) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'images' => [
                    "A post may not have more than 10 images in total. "
                    . "This post already has {$existingCount} image(s); "
                    . "uploading {$incomingCount} more would exceed the limit.",
                ],
            ]);
        }

        $maxSort = $post->images()->max('sort_order') ?? -1;
        $created = [];

        foreach ($request->file('images') as $i => $file) {
            $path  = $file->store('posts/images', 'public');
            $image = PostImage::create([
                'post_id'    => $post->id,
                'path'       => $path,
                'sort_order' => $maxSort + $i + 1,
            ]);

            $created[] = [
                'id'         => $image->id,
                'url'        => $post->resolveImageUrl($path),
                'sort_order' => $image->sort_order,
            ];
        }

        return response()->json(['data' => $created]);
    }

    /**
     * DELETE /api/admin/posts/{post}/images/{image}
     * Delete a single body image record and remove the file.
     */
    public function destroyImage(Post $post, PostImage $image): JsonResponse
    {
        if ($image->post_id !== $post->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/admin/posts/{post}/images/reorder
     * Accept { order: [imageId, ...] } and reassign sort_order by position.
     */
    public function reorderImages(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $postImageIds = $post->images()->pluck('id')->all();
        $submittedIds = $request->input('order', []);

        foreach ($submittedIds as $imageId) {
            if (! in_array((int) $imageId, $postImageIds, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => ["Image ID {$imageId} does not belong to this post."],
                ]);
            }
        }

        DB::transaction(function () use ($submittedIds, $post) {
            foreach ($submittedIds as $position => $imageId) {
                PostImage::where('id', $imageId)
                    ->where('post_id', $post->id)
                    ->update(['sort_order' => $position]);
            }
        });

        $images = $post->images()->orderBy('sort_order')->get()->map(fn ($img) => [
            'id'         => $img->id,
            'url'        => $post->resolveImageUrl($img->path),
            'sort_order' => $img->sort_order,
        ])->values();

        return response()->json(['data' => $images]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Sanitize post body HTML using the 'posts' purifier profile.
     *
     * `allow` and `allowfullscreen` are HTML5 iframe attributes absent from
     * HTMLPurifier's default HTML4 doctype definition. They cannot be added via
     * HTML.Allowed alone (that would throw an ErrorException at runtime).
     * Instead we register them through a custom HTMLPurifier definition using
     * mews/purifier 3.x's closure hook (`Purifier::clean($html, $config, Closure)`).
     *
     * The closure uses `maybeGetRawHTMLDefinition()` (the optimized path) which
     * returns null on a cache hit (definition already registered) or a raw
     * HTMLPurifier_HTMLDefinition on a cache miss. This avoids the E_USER_WARNING
     * that `getHTMLDefinition(true)` emits when DefinitionID is set but the
     * non-optimized raw path is used — Laravel converts that warning to an
     * ErrorException in production-strict mode.
     *
     * Config keys HTML.DefinitionID and HTML.DefinitionRev (set in purifier.php)
     * are required for the optimized cache path. Bump DefinitionRev whenever the
     * definition changes.
     *
     * mews/purifier ≥ 3.3.x supports the third closure argument. The installed
     * version is 3.4.4 (verified via `composer show mews/purifier`).
     */
    private function cleanBody(string $html): string
    {
        return Purifier::clean($html, 'posts', function (\HTMLPurifier_Config $config): void {
            // maybeGetRawHTMLDefinition uses the optimized path:
            // returns null when the cached definition is already available
            // (no edits needed), or a raw HTMLPurifier_HTMLDefinition otherwise.
            $def = $config->maybeGetRawHTMLDefinition();
            if ($def) {
                $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
                $def->addAttribute('iframe', 'allow', 'Text');
            }
        });
    }

    /**
     * Generate a slug that is unique in the posts table.
     * Counter starts at 2 — first collision yields "my-post-2".
     *
     * @param  int|null  $excludeId  Post ID to exclude (for updates)
     */
    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug    = $base;
        $counter = 2;

        while (true) {
            $query = Post::where('slug', $slug);

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
