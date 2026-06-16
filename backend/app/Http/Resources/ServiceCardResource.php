<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'slug'              => $this->slug,
            'description'       => $this->description
                ? mb_strimwidth($this->description, 0, 150, '...')
                : null,
            'price'             => number_format((float) $this->price, 2, '.', ''),
            'duration_hours'    => $this->duration_hours,
            'availability_type' => $this->availability_type,
            'is_published'      => (bool) $this->is_published,
            'thumbnail'         => $this->thumbnailUrl,
            'images_count'      => $this->images_count ?? $this->images->count(),
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
}
