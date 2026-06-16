<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'email'  => $this->email,
            'avatar' => $this->avatarUrl(),
            'role'   => $this->role,
            // Lets the client hide the password-change form for Google-only accounts
            'has_password' => ! is_null($this->password),
        ];
    }
}
