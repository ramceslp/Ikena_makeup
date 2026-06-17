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
            // Course order: include `course` key (unchanged behavior)
            $this->mergeWhen($this->relationLoaded('course') && $this->course !== null, [
                'course' => [
                    'id'        => $this->course?->id,
                    'title'     => $this->course?->title,
                    'slug'      => $this->course?->slug,
                    'thumbnail' => $this->course?->thumbnail,
                ],
            ]),
            // Appointment order: include `appointment` key with service info
            $this->mergeWhen($this->relationLoaded('appointment') && $this->appointment !== null, [
                'appointment' => [
                    'service_title'       => $this->appointment?->service?->title,
                    'scheduled_date'      => $this->appointment?->scheduled_date?->format('Y-m-d'),
                    'scheduled_time'      => $this->appointment?->scheduled_time,
                    'deposit_amount_cents' => $this->appointment?->deposit_amount_cents,
                ],
            ]),
        ];
    }
}
