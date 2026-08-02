<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'service'             => $this->whenLoaded('service', fn () => [
                'title' => $this->service->title,
            ]),
            'user'                => $this->whenLoaded('user', fn () => [
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'scheduled_date'      => $this->scheduled_date?->format('Y-m-d'),
            'scheduled_time'      => $this->scheduled_time,
            'status'              => $this->status,
            'payment_mode'        => $this->payment_mode,
            'deposit_amount_cents' => $this->deposit_amount_cents,
            // PR1b (design D1) — the two write-once money channels, surfaced
            // so the admin panel can show what was actually collected/settled
            // instead of only the quoted deposit_amount_cents above.
            'deposit_collected_cents' => $this->deposit_collected_cents,
            'deposit_collected_at'    => $this->deposit_collected_at,
            'settled_amount_cents'    => $this->settled_amount_cents,
            'settled_at'              => $this->settled_at,
            // FIX 5 — whatsapp field added for admin contact visibility
            'whatsapp'            => $this->whatsapp,
            'order'               => $this->whenLoaded('order', fn () => [
                'status'       => $this->order->status,
                'amount_cents' => $this->order->amount_cents,
            ]),
            'cancelled_at'        => $this->cancelled_at,
        ];
    }
}
