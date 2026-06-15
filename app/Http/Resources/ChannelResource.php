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
            'meta' => $this->meta,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
