<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MyAppointmentResource — one row of the authenticated user's own agenda
 * (GET /api/profile/appointments).
 *
 * Separate from AppointmentResource, which serves the ADMIN appointment list:
 * that one carries the customer's name/email and payment_mode, none of which
 * belongs in a response the customer requests about themselves. This shape is
 * built for the agenda screen instead — enough service detail to render a card
 * (title, slug, thumbnail, duration) plus the payment state of the linked
 * order, so "reservada, depósito pendiente" and "reservada y pagada" can be
 * told apart.
 */
class MyAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            // Normalized to HH:MM so MySQL ('10:00:00') and SQLite ('10:00')
            // render identically on the client — the same normalization
            // Appointment::makeSlotKey() applies for the slot key.
            'scheduled_time' => $this->scheduled_time ? substr($this->scheduled_time, 0, 5) : null,
            'scheduled_end_time' => $this->scheduled_end_time ? substr($this->scheduled_end_time, 0, 5) : null,
            'status' => $this->status,
            'deposit_amount_cents' => $this->deposit_amount_cents,
            'whatsapp' => $this->whatsapp,
            'cancelled_at' => $this->cancelled_at,
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'title' => $this->service->title,
                'slug' => $this->service->slug,
                'thumbnail' => $this->service->thumbnailUrl,
                'duration_hours' => $this->service->duration_hours,
            ] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'status' => $this->order->status,
                'amount_cents' => $this->order->amount_cents,
                'currency' => $this->order->currency,
            ] : null),
        ];
    }
}
