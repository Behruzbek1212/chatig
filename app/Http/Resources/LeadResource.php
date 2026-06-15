<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'city' => $this->city,
            'phone' => $this->phone,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'conversation_id' => $this->conversation_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
