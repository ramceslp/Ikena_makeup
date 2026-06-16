<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'price'             => number_format((float) $this->price, 2, '.', ''),
            'duration_hours'    => $this->duration_hours,
            'availability_type' => $this->availability_type,
            'is_published'      => (bool) $this->is_published,
            'thumbnail'         => $this->thumbnailUrl,
            'images_count'      => $this->images->count(),
            'images'            => $this->images->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => $this->resolveUrl($img->path),
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
        ];
    }

    /**
     * Resolve a stored path to an absolute URL.
     * Pass-through if the path is already an absolute HTTP URL.
     */
    private function resolveUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
