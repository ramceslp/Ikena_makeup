<?php

namespace App\Http\Resources\Instructor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorCourseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'slug'               => $this->slug,
            'description'        => $this->description,
            'price'              => number_format($this->price, 2, '.', ''),
            'thumbnail'          => $this->thumbnail,
            'is_published'       => $this->is_published,
            'category_id'        => $this->category_id,
            'offers_certificate' => (bool) $this->offers_certificate,
            'delivery_mode'      => $this->delivery_mode,
            'starts_on'          => $this->starts_on?->toDateString(),
            'ends_on'            => $this->ends_on?->toDateString(),
            'total_hours'        => $this->total_hours,
            'total_lessons'      => $this->lessons_count ?? 0,
            'sections'           => $this->whenLoaded('sections', function () {
                return $this->sections->map(function ($section) {
                    return [
                        'id'       => $section->id,
                        'title'    => $section->title,
                        'position' => $section->position,
                        'lessons'  => $section->lessons->map(fn ($lesson) => [
                            'id'          => $lesson->id,
                            'title'       => $lesson->title,
                            'description' => $lesson->description,
                            'video_url'   => $lesson->video_url,
                            // The author always sees the raw link — the
                            // scheduled-window rule guards the STUDENT view.
                            'meeting_url' => $lesson->meeting_url,
                            'starts_at'   => $lesson->starts_at?->toISOString(),
                            'duration'    => $lesson->duration,
                            'position'    => $lesson->position,
                            'is_free'     => $lesson->is_free,
                            'is_practice' => $lesson->is_practice,
                        ]),
                    ];
                });
            }),
        ];
    }
}
