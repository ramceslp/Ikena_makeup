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

            // The join link is served ONLY inside the scheduled window. This
            // is the whole point of keeping it out of video_url: a recording
            // URL is harmless once enrolled, a live room is not — it stays
            // joinable by anyone who ever saw it, including after a refund.
            // Outside the window the client gets the schedule and nothing else.
            'meeting_url'          => $this->resource->meetingWindowIsOpen()
                ? $this->meeting_url
                : null,
            'starts_at'            => $this->starts_at?->toISOString(),
            'meeting_available_at' => $this->resource->meetingAvailableAt()?->toISOString(),
            'is_live_session'      => $this->starts_at !== null,

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
