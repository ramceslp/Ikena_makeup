<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge([
            'id'              => $this->id,
            'title'           => $this->title,
            'slug'            => $this->slug,
            'excerpt'         => $this->excerpt,
            'type'            => $this->type,
            'is_featured'     => (bool) $this->is_featured,
            'cta_label'       => $this->cta_label,
            'cta_url'         => $this->cta_url,
            'published_at'    => $this->published_at?->toIso8601String(),
            'cover_image_url' => $this->cover_image_url,
            'body'            => $this->body,
            'author'          => $this->whenLoaded('author', function () {
                return $this->author
                    ? ['id' => $this->author->id, 'name' => $this->author->name]
                    : null;
            }),
            'images'          => $this->whenLoaded('images', function () {
                return $this->images->map(fn ($img) => [
                    'id'         => $img->id,
                    'url'        => $this->resource->resolveImageUrl($img->path),
                    'sort_order' => $img->sort_order,
                ])->values()->toArray();
            }),
        ], $request->user()?->isAdmin() ? [
            'is_published' => (bool) $this->is_published,
            'author_id'    => $this->author_id,
        ] : []);
    }
}
