<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\Store;
use App\Support\PhoneNumber;

class LeadService
{
    /**
     * Create or update a lead. Leads may be collected across several messages,
     * so fields are merged (only non-empty incoming values overwrite). Dedup is
     * by conversation first, then by phone within the store.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrUpdate(Store $store, array $data): Lead
    {
        $phone = isset($data['phone']) && $data['phone'] !== ''
            ? (PhoneNumber::normalize((string) $data['phone']) ?? (string) $data['phone'])
            : null;

        $attributes = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'city' => $data['city'] ?? null,
            'phone' => $phone,
        ], fn ($v) => $v !== null && $v !== '');

        $lead = $this->findExisting($store, $data, $phone);

        if ($lead) {
            $lead->fill($attributes);
            $lead->save();

            return $lead;
        }

        return Lead::query()->create([
            'store_id' => $store->id,
            'customer_id' => $data['customer_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'status' => 'new',
            ...$attributes,
        ]);
    }

    private function findExisting(Store $store, array $data, ?string $phone): ?Lead
    {
        $base = Lead::withoutGlobalScope('store')->where('store_id', $store->id);

        if (! empty($data['conversation_id'])) {
            $found = (clone $base)->where('conversation_id', $data['conversation_id'])->first();
            if ($found) {
                return $found;
            }
        }

        if ($phone !== null) {
            return (clone $base)->where('phone', $phone)->first();
        }

        return null;
    }
}
