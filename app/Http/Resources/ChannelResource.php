<?php

namespace App\Http\Resources;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Channel */
class ChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'username' => $this->username,
            'status' => $this->status,
            'connected' => $this->isConnected(),
            // Surfaced as stable top-level fields so the SPA can poll AI-setup
            // progress without coupling to the meta blob's internal shape.
            'ai_setup_status' => $this->meta['ai_setup_status'] ?? null,
            'ai_setup_step' => $this->meta['ai_setup_step'] ?? null,
            'meta' => $this->meta,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
