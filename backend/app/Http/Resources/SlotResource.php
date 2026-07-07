<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SlotResource
 *
 * Represents a public-facing bookable occurrence, produced by
 * VenueAvailabilityResolver::resolve() (an associative array with keys:
 * slot_id, date_label, start_time, capacity_remaining, is_near_capacity,
 * warning_message — see VAVL-001/002/003).
 *
 * Prior to the venue-agenda-concurrency change, this resource also accepted
 * a legacy ServiceSlot Eloquent model directly (per-service slot admin CRUD).
 * That branch was removed in Slice 5 cleanup once the legacy ServiceSlot
 * model/controller/routes were dropped.
 */
class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Keys: slot_id, date_label, start_time, capacity_remaining,
        //       is_near_capacity, warning_message (VAVL-003, additive/back-compatible)
        return [
            'id'                 => $this->resource['slot_id'],
            'date_label'         => $this->resource['date_label'],
            'start_time'         => $this->resource['start_time'],
            'capacity_remaining' => $this->resource['capacity_remaining'],
            'is_near_capacity'   => $this->resource['is_near_capacity'] ?? false,
            'warning_message'    => $this->resource['warning_message'] ?? null,
        ];
    }
}
