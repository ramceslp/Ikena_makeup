<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge([
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug,
            'description' => $this->description
                ? mb_strimwidth($this->description, 0, 150, '...')
                : null,
            'price'       => number_format((float) $this->price, 2, '.', ''),
            'stock_qty'   => (int) $this->stock_qty,
            'stock_state' => $this->stock_state,
            'thumbnail'   => $this->thumbnailUrl,
            'images_count' => $this->images_count ?? $this->images->count(),
            'category'    => $this->whenLoaded('category', function () {
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
