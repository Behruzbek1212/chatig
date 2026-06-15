<?php

namespace App\Http\Resources;

use App\Models\AiConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiConfig */
class AiConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'system_prompt' => $this->system_prompt,
            'mode' => $this->mode,
            'working_hours' => $this->working_hours,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
