<?php

namespace App\Http\Resources\Instructor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorDashboardResource extends JsonResource
{
    /**
     * The resource instance is an array with the aggregated data.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kpis'           => $this->resource['kpis'],
            'sales_over_time' => $this->resource['sales_over_time'],
        ];
    }
}
