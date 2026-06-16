<?php

namespace App\Http\Resources\Instructor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorCourseCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'slug'           => $this->slug,
            'price'          => number_format($this->price, 2, '.', ''),
            'thumbnail'      => $this->thumbnail,
            'is_published'   => $this->is_published,
            'sections_count' => $this->sections_count ?? 0,
            'lessons_count'  => $this->lessons_count ?? 0,
            'students_count' => $this->students_count ?? 0,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
