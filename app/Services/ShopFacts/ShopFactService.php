<?php

namespace App\Services\ShopFacts;

use App\Models\ShopFact;
use App\Models\Store;
use Illuminate\Support\Collection;

class ShopFactService
{
    public function __construct(private readonly ShopFactEmbeddingService $embeddings) {}

    /**
     * @return Collection<int, ShopFact>
     */
    public function list(Store $store): Collection
    {
        return ShopFact::query()
            ->where('store_id', $store->id)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Store $store, array $data): ShopFact
    {
        $fact = ShopFact::create([
            'store_id' => $store->id,
            'label' => $data['label'],
            'value' => $data['value'],
            'display_order' => $data['display_order'] ?? 0,
        ]);

        $this->embeddings->embed($fact);

        return $fact->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShopFact $fact, array $data): ShopFact
    {
        $fact->fill(collect($data)->only(['label', 'value', 'display_order'])->all());
        $fact->save();

        $this->embeddings->embed($fact);

        return $fact->refresh();
    }

    public function delete(ShopFact $fact): void
    {
        $fact->delete();
    }

    /**
     * Keyword search used as a fallback when embeddings are unavailable.
     *
     * @return Collection<int, ShopFact>
     */
    public function keywordSearch(Store $store, string $query, int $limit = 5): Collection
    {
        return ShopFact::query()
            ->where('store_id', $store->id)
            ->where(function ($q) use ($query): void {
                $q->where('label', 'like', "%{$query}%")
                    ->orWhere('value', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Semantic (pgvector) search by natural-language query, falling back to
     * keyword search when embeddings are unavailable (non-pgsql, no credit,
     * or no embedded facts yet).
     *
     * @return Collection<int, ShopFact>
     */
    public function semanticSearch(Store $store, string $query, int $limit = 5): Collection
    {
        if (! $this->embeddings->enabled()) {
            return $this->keywordSearch($store, $query, $limit);
        }

        $literal = $this->embeddings->queryLiteral($query);

        if ($literal === null) {
            return $this->keywordSearch($store, $query, $limit);
        }

        $results = ShopFact::query()
            ->where('store_id', $store->id)
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?::vector', [$literal])
            ->limit($limit)
            ->get();

        return $results->isNotEmpty() ? $results : $this->keywordSearch($store, $query, $limit);
    }
}
