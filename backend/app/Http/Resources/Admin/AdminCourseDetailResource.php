<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail shape for the admin metadata editor.
 *
 * Deliberately omits sections and lessons: deep authoring is delegated to the
 * instructor editor (see CoursePolicy::manage), so duplicating the nested tree
 * here would create a second, drifting representation of the same content.
 */
class AdminCourseDetailResource extends JsonResource
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
            'instructor_id'      => $this->instructor_id,
            'instructor'         => $this->whenLoaded('instructor', fn () => $this->instructor ? [
                'id'   => $this->instructor->id,
                'name' => $this->instructor->name,
            ] : null),
            'sections_count'     => $this->sections_count ?? 0,
            'lessons_count'      => $this->lessons_count ?? 0,
            'students_count'     => $this->students_count ?? 0,
            'created_at'         => $this->created_at?->toISOString(),
        ];
    }
}
