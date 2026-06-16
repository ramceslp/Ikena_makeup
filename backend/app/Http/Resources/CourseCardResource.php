<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $avgRating = $this->reviews_avg_rating !== null
            ? round((float) $this->reviews_avg_rating, 1)
            : null;

        $data = [
            'id'             => $this->id,
            'title'          => $this->title,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'price'          => number_format($this->price, 2, '.', ''),
            'thumbnail'      => $this->thumbnail,
            'instructor'     => [
                'id'   => $this->instructor->id,
                'name' => $this->instructor->name,
            ],
            'category'       => $this->whenLoaded('category', function () {
                return $this->category
                    ? [
                        'id'   => $this->category->id,
                        'name' => $this->category->name,
                        'slug' => $this->category->slug,
                    ]
                    : null;
            }),
            'lessons_count'  => $this->lessons_count ?? 0,
            'sections_count' => $this->sections_count ?? 0,
            'average_rating' => $avgRating,
            'reviews_count'  => $this->reviews_count ?? 0,
            'is_bestseller'  => (bool) ($this->resource->is_bestseller ?? false),
            'offers_certificate' => (bool) $this->offers_certificate,
        ];

        // Only expose is_enrolled when request is authenticated
        if ($request->user()) {
            $data['is_enrolled'] = $this->resource->is_enrolled ?? false;
        }

        return $data;
    }
}
