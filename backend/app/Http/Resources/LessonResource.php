<?php

namespace App\Http\Resources;

use App\Http\Resources\PracticeSubmissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
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
            'is_free'      => $this->is_free,
            'is_practice'  => $this->is_practice,
            'completed'    => $this->resource->is_completed ?? false,
            'my_submission' => $this->resource->my_submission
                ? new PracticeSubmissionResource($this->resource->my_submission)
                : null,
        ];
    }
}
