<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Front 'sender' kutadi: customer | ai | agent (owner).
        $sender = match ($this->role) {
            'owner' => 'agent',
            'ai' => 'ai',
            default => 'customer',
        };

        return [
            'id' => $this->id,
            'sender' => $sender,
            'text' => $this->content,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
