<?php

namespace App\Services\Auth\Ai;

use App\Models\AiConfig;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class AiSettingsService
{
    public function current(Store $store): ?AiConfig
    {
        return AiConfig::where('store_id', $store->id)
            ->where('is_active', true)
            ->latest('version')
            ->first();
    }

    /**
     * Save a new active config version, deactivating the previous one.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Store $store, array $data): AiConfig
    {
        return DB::transaction(function () use ($store, $data): AiConfig {
            $maxVersion = (int) AiConfig::where('store_id', $store->id)->max('version');

            AiConfig::where('store_id', $store->id)->update(['is_active' => false]);

            return AiConfig::create([
                'store_id' => $store->id,
                'system_prompt' => $data['system_prompt'],
                'mode' => $data['mode'] ?? 'suggest',
                'working_hours' => $data['working_hours'] ?? null,
                'raw_inputs' => $data['raw_inputs'] ?? null,
                'version' => $maxVersion + 1,
                'is_active' => true,
            ]);
        });
    }
}
