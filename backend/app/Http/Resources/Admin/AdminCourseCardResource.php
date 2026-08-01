<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row shape for the admin catalog table.
 *
 * Differs from InstructorCourseCardResource in one way that matters: it carries
 * the owning instructor, because the admin list spans every instructor while
 * the instructor list is implicitly single-owner.
 */
class AdminCourseCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'slug'               => $this->slug,
            'price'              => number_format($this->price, 2, '.', ''),
            'thumbnail'          => $this->thumbnail,
            'is_published'       => $this->is_published,
            'category_id'        => $this->category_id,
            'category'           => $this->whenLoaded('category', fn () => $this->category ? [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'offers_certificate' => (bool) $this->offers_certificate,
            'delivery_mode'      => $this->delivery_mode,
            'starts_on'          => $this->starts_on?->toDateString(),
            'ends_on'            => $this->ends_on?->toDateString(),
            'total_hours'        => $this->total_hours,
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
