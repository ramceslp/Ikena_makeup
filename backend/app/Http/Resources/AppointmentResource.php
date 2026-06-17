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
            'order'               => $this->whenLoaded('order', fn () => [
                'status'       => $this->order->status,
                'amount_cents' => $this->order->amount_cents,
            ]),
            'cancelled_at'        => $this->cancelled_at,
        ];
    }
}
