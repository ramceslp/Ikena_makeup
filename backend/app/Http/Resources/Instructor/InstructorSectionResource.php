<?php

namespace App\Http\Resources\Instructor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'title'    => $this->title,
            'position' => $this->position,
        ];
    }
}
