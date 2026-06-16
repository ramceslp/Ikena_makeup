<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'rating'     => $this->rating,
            'body'       => $this->body,
            'created_at' => $this->created_at,
            'user'       => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
        ];
    }
}
