<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'amount_cents' => $this->amount_cents,
            'currency'     => $this->currency,
            'paid_at'      => $this->paid_at,
            'created_at'   => $this->created_at,
            'course'       => $this->whenLoaded('course', fn () => [
                'id'        => $this->course->id,
                'title'     => $this->course->title,
                'slug'      => $this->course->slug,
                'thumbnail' => $this->course->thumbnail,
            ]),
        ];
    }
}
