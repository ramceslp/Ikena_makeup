<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AgendaBlockResource
 *
 * Represents a venue agenda block for admin CRUD (VAGA-001). Times are
 * normalized to H:i (existing substr(0,5) convention) regardless of how
 * the underlying driver stores the `time` column.
 */
class AgendaBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'day_of_week'       => $this->day_of_week,
            'specific_date'     => $this->specific_date?->format('Y-m-d'),
            'open_time'         => substr($this->open_time, 0, 5),
            'close_time'        => substr($this->close_time, 0, 5),
            'concurrency_limit' => $this->concurrency_limit,
            'soft_threshold'    => $this->soft_threshold,
            'is_blocked'        => (bool) $this->is_blocked,
        ];
    }
}
