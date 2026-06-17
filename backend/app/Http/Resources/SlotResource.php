<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'service_id'        => $this->service_id,
            'day_of_week'       => $this->day_of_week,
            'specific_date'     => $this->specific_date?->format('Y-m-d'),
            'start_time'        => $this->start_time,
            'capacity'          => $this->capacity,
            'is_blocked'        => (bool) $this->is_blocked,
        ];
    }
}
