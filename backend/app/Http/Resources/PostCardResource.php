<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCardResource extends JsonResource
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
        ], $request->user()?->isAdmin() ? [
            'is_published' => (bool) $this->is_published,
            'author_id'    => $this->author_id,
        ] : []);
    }
}
