<?php

namespace App\Services\Llm;

use App\Services\Llm\Contracts\EmbeddingClient;

/**
 * Deterministic embeddings for the 'fake' driver and tests — derived from the
 * text hash, so identical text yields identical vectors (no network).
 */
class FakeEmbeddingClient implements EmbeddingClient
{
    public function __construct(private readonly int $dimensions = 1536) {}

    public function embed(string $text): array
    {
        $seed = crc32($text);
        $vector = [];

        for ($i = 0; $i < $this->dimensions; $i++) {
            $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
            $vector[] = ($seed % 1000) / 1000;
        }

        return $vector;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
