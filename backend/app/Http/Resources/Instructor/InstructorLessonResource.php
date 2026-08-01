<?php

namespace App\Http\Resources\Instructor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'section_id'  => $this->section_id,
            'title'       => $this->title,
            'description' => $this->description,
            'video_url'   => $this->video_url,
            // The author always sees the raw link — the scheduled-window rule
            // in LessonResource guards the STUDENT view, not this one.
            'meeting_url' => $this->meeting_url,
            // Academy wall-clock, NOT UTC, and deliberately the exact shape a
            // datetime-local input round-trips. Laravel does not convert a
            // datetime to the app timezone on save, so an ISO string ending in
            // Z would be stored verbatim and shift the session by the UTC
            // offset. The student-facing LessonResource keeps ISO instants,
            // which is what a countdown needs.
            'starts_at'   => $this->starts_at?->format('Y-m-d\TH:i'),
            'duration'    => $this->duration,
            'position'    => $this->position,
            'is_free'     => $this->is_free,
            'is_practice' => $this->is_practice,
        ];
    }
}
