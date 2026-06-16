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
            'duration'    => $this->duration,
            'position'    => $this->position,
            'is_free'     => $this->is_free,
            'is_practice' => $this->is_practice,
        ];
    }
}
