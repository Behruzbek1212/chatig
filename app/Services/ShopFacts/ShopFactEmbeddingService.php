<?php

namespace App\Services\ShopFacts;

use App\Models\ShopFact;
use App\Services\Llm\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates and stores pgvector embeddings for shop facts (PostgreSQL only).
 * All operations are non-fatal: embedding failures (e.g. no OpenAI credit)
 * never block ShopFact CRUD — semantic search just falls back to keyword.
 */
class ShopFactEmbeddingService
{
    public function __construct(private readonly EmbeddingClient $client) {}

    public function enabled(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * Compute and persist the embedding for a shop fact. No-op on non-pgsql
     * or on any error.
     */
    public function embed(ShopFact $fact): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $vector = $this->client->embed($this->factText($fact));

            DB::statement(
                'UPDATE shop_facts SET embedding = ?::vector WHERE id = ?',
                [$this->toLiteral($vector), $fact->id],
            );
        } catch (Throwable $e) {
            Log::warning('Shop fact embedding failed', ['shop_fact_id' => $fact->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Embedding literal for a free-text query, or null if unavailable.
     */
    public function queryLiteral(string $text): ?string
    {
        try {
            return $this->toLiteral($this->client->embed($text));
        } catch (Throwable) {
            return null;
        }
    }

    private function factText(ShopFact $fact): string
    {
        return trim($fact->label.' '.$fact->value);
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function toLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
