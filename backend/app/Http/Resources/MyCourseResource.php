<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalLessons     = $this->resource->total_lessons ?? 0;
        $completedLessons = $this->resource->completed_lessons ?? 0;
        $percentage       = $totalLessons > 0
            ? (int) round($completedLessons / $totalLessons * 100)
            : 0;

        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'slug'                => $this->slug,
            'thumbnail'           => $this->thumbnail,
            'instructor'          => [
                'id'   => $this->instructor->id,
                'name' => $this->instructor->name,
            ],
            'total_lessons'       => $totalLessons,
            'completed_lessons'   => $completedLessons,
            'progress_percentage' => $percentage,
        ];
    }
}
