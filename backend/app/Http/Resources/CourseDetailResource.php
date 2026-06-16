<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isEnrolled = $this->resource->is_enrolled ?? false;

        $avgRating = $this->reviews_avg_rating !== null
            ? round((float) $this->reviews_avg_rating, 1)
            : null;

        $myReviewData = null;
        if ($user && isset($this->resource->my_review) && $this->resource->my_review !== null) {
            $mr = $this->resource->my_review;
            $myReviewData = [
                'id'     => $mr->id,
                'rating' => $mr->rating,
                'body'   => $mr->body,
            ];
        }

        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'slug'               => $this->slug,
            'description'        => $this->description,
            'price'              => number_format($this->price, 2, '.', ''),
            'thumbnail'          => $this->thumbnail,
            'instructor'         => [
                'id'   => $this->instructor->id,
                'name' => $this->instructor->name,
            ],
            'category'           => $this->whenLoaded('category', function () {
                return $this->category
                    ? [
                        'id'   => $this->category->id,
                        'name' => $this->category->name,
                        'slug' => $this->category->slug,
                    ]
                    : null;
            }),
            'total_lessons'      => $this->lessons_count ?? 0,
            'is_enrolled'        => $user ? $isEnrolled : false,
            'average_rating'     => $avgRating,
            'reviews_count'      => $this->reviews_count ?? 0,
            'is_bestseller'      => (bool) ($this->resource->is_bestseller ?? false),
            'offers_certificate' => (bool) $this->offers_certificate,
            'my_review'          => $myReviewData,
            'sections'           => $this->sections->map(function ($section) use ($user, $isEnrolled) {
                return [
                    'id'       => $section->id,
                    'title'    => $section->title,
                    'position' => $section->position,
                    'lessons'  => $section->lessons->map(function ($lesson) use ($user, $isEnrolled) {
                        $lessonData = [
                            'id'       => $lesson->id,
                            'title'    => $lesson->title,
                            'position' => $lesson->position,
                            'is_free'  => $lesson->is_free,
                            'duration' => $lesson->duration,
                        ];

                        // Only include completed status when authenticated and enrolled
                        if ($user && $isEnrolled) {
                            $lessonData['completed'] = $lesson->is_completed ?? false;
                        }

                        return $lessonData;
                    }),
                ];
            }),
        ];
    }
}
