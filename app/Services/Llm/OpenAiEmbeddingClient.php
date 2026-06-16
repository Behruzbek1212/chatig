<?php

namespace App\Services\Llm;

use App\Services\Llm\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiEmbeddingClient implements EmbeddingClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $dimensions,
    ) {}

    public function embed(string $text): array
    {
        if (! $this->apiKey) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->retry(2, 500)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $this->dimensions,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embedding failed: '.$response->body());
        }

        return $response->json('data.0.embedding', []);
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
