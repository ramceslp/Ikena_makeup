<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge([
            'id'                => $this->id,
            'title'             => $this->title,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'price'             => number_format((float) $this->price, 2, '.', ''),
            'duration_hours'    => $this->duration_hours,
            'availability_type' => $this->availability_type,
            'thumbnail'         => $this->thumbnailUrl,
            'images_count'      => $this->images->count(),
            // Delegate URL resolution to the model — single source of truth for path → URL
            'images'            => $this->images->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => $this->resource->resolveImageUrl($img->path),
                'sort_order' => $img->sort_order,
            ])->values()->toArray(),
            'category'          => $this->whenLoaded('category', function () {
                return $this->category
                    ? [
                        'id'   => $this->category->id,
                        'name' => $this->category->name,
                        'slug' => $this->category->slug,
                    ]
                    : null;
            }),
        ], $request->user()?->isAdmin() ? ['is_published' => (bool) $this->is_published] : []);
    }
}
