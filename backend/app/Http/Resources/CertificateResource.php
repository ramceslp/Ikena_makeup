<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code'            => $this->code,
            'issued_at'       => $this->issued_at,
            'student_name'    => $this->whenLoaded('user', fn () => $this->user->name),
            'course_title'    => $this->whenLoaded('course', fn () => $this->course->title),
            'instructor_name' => $this->whenLoaded('course', fn () => $this->course->instructor?->name),
        ];
    }
}
