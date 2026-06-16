<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PracticeSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'lesson_id'  => $this->lesson_id,
            'status'     => $this->status,
            'feedback'   => $this->feedback,
            'before_url' => $this->before_url,
            'after_url'  => $this->after_url,
            'created_at' => $this->created_at,
            'graded_at'  => $this->graded_at,
            'user'       => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar,
            ]),
            'lesson'     => $this->whenLoaded('lesson', fn () => [
                'id'    => $this->lesson->id,
                'title' => $this->lesson->title,
            ]),
        ];
    }
}
