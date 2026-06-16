<?php

namespace App\Services\Llm\Contracts;

interface EmbeddingClient
{
    /**
     * Return the embedding vector for the given text.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array;

    public function dimensions(): int;
}
