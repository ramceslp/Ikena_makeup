<?php

namespace App\Http\Resources\Instructor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorCourseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'slug'          => $this->slug,
            'description'   => $this->description,
            'price'         => number_format($this->price, 2, '.', ''),
            'thumbnail'     => $this->thumbnail,
            'is_published'  => $this->is_published,
            'total_lessons' => $this->lessons_count ?? 0,
            'sections'      => $this->whenLoaded('sections', function () {
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
                            'duration'    => $lesson->duration,
                            'position'    => $lesson->position,
                            'is_free'     => $lesson->is_free,
                        ]),
                    ];
                });
            }),
        ];
    }
}
