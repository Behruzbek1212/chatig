<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Services\Llm\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates and stores pgvector embeddings for products (PostgreSQL only).
 * All operations are non-fatal: embedding failures (e.g. no OpenAI credit)
 * never block product CRUD — semantic search just falls back to keyword.
 */
class ProductEmbeddingService
{
    public function __construct(private readonly EmbeddingClient $client) {}

    public function enabled(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * Compute and persist the embedding for a product. No-op on non-pgsql or
     * on any error.
     */
    public function embed(Product $product): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $vector = $this->client->embed($this->productText($product));

            DB::statement(
                'UPDATE products SET embedding = ?::vector WHERE id = ?',
                [$this->toLiteral($vector), $product->id],
            );
        } catch (Throwable $e) {
            Log::warning('Product embedding failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
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

    private function productText(Product $product): string
    {
        return trim(implode(' ', array_filter([
            $product->name,
            $product->brand,
            $product->category,
            $product->description,
        ])));
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function toLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
