<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PushNotificationLogResource — one row of the admin notification history
 * (push-notifications Slice 3).
 */
class PushNotificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'type'             => $this->type,
            'title'            => $this->title,
            'body'             => $this->body,
            'route'            => $this->data['route'] ?? null,
            'audience'         => $this->audience,
            'status'           => $this->status,
            'recipients_count' => $this->recipients_count,
            'success_count'    => $this->success_count,
            'failure_count'    => $this->failure_count,

            // Null for automatic triggers — the UI renders those as "Sistema".
            'sent_by'          => $this->whenLoaded(
                'sender',
                fn (): ?array => $this->sender === null ? null : [
                    'id'   => $this->sender->id,
                    'name' => $this->sender->name,
                ],
            ),

            // ISO-8601 in the app timezone (America/Guayaquil, pinned by
            // ConfigTimezoneTest) so the panel shows local time without
            // client-side guessing.
            'sent_at'          => $this->sent_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
