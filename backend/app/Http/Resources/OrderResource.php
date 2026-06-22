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
            // Discriminator the client switches on (course | appointment | product_cart).
            'type'         => $this->type,
            'status'       => $this->status,
            'amount_cents' => $this->amount_cents,
            'currency'     => $this->currency,
            'paid_at'      => $this->paid_at,
            'created_at'   => $this->created_at,
            // Product cart order: include `items` (snapshot fields, no product join needed).
            $this->mergeWhen($this->type === 'product_cart' && $this->relationLoaded('items'), fn () => [
                'items' => $this->items->map(fn ($item) => [
                    'product_title'    => $item->product_title,
                    'quantity'         => $item->quantity,
                    'line_total_cents' => $item->line_total_cents,
                ])->values(),
            ]),
            // Course order: include `course` key (unchanged behavior)
            // FIX 3 — second arg is a closure so the array is only evaluated when the
            // condition is true, preventing N+1 lazy-load on unloaded relations.
            $this->mergeWhen($this->relationLoaded('course') && $this->course !== null, fn () => [
                'course' => [
                    'id'        => $this->course?->id,
                    'title'     => $this->course?->title,
                    'slug'      => $this->course?->slug,
                    'thumbnail' => $this->course?->thumbnail,
                ],
            ]),
            // Appointment order: include `appointment` key with service info
            // FIX 3 — closure defers evaluation, avoiding lazy-load when not needed.
            $this->mergeWhen($this->relationLoaded('appointment') && $this->appointment !== null, fn () => [
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
