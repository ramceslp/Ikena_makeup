<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SlotResource
 *
 * Represents a service slot. The `date_label` and `capacity_remaining` fields
 * are populated when the resource is created from a resolver occurrence array
 * (public available-slots endpoint). When used from admin context (slot CRUD),
 * those fields may be absent from the underlying data — they are included only
 * when present.
 *
 * The resource accepts EITHER a ServiceSlot Eloquent model OR an associative
 * array from SlotAvailabilityResolver (with keys: slot_id, date_label,
 * start_time, capacity_remaining). A wrapper static factory handles the array case.
 */
class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // When resource wraps an Eloquent ServiceSlot model
        if ($this->resource instanceof \App\Models\ServiceSlot) {
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

        // When resource wraps a resolver occurrence array
        // Keys: slot_id, date_label, start_time, capacity_remaining
        return [
            'id'                 => $this->resource['slot_id'],
            'date_label'         => $this->resource['date_label'],
            'start_time'         => $this->resource['start_time'],
            'capacity_remaining' => $this->resource['capacity_remaining'],
        ];
    }
}
