<?php

namespace App\Services\Llm;

use App\Services\Llm\Contracts\LlmClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient implements LlmClient
{
    public function __construct(private readonly ?string $apiKey) {}

    public function chat(string $model, array $messages, array $options = []): string
    {
        $response = $this->request([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        return (string) $response->json('choices.0.message.content', '');
    }

    public function chatWithTools(string $model, array $messages, array $tools, array $options = []): LlmTurn
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.5,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = $this->request($payload);
        $message = $response->json('choices.0.message', []);

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $toolCalls[] = new LlmToolCall(
                id: $call['id'],
                name: $call['function']['name'],
                arguments: $this->decodeArguments($call['function']['arguments'] ?? '{}'),
            );
        }

        return new LlmTurn(
            content: $message['content'] ?? null,
            toolCalls: $toolCalls,
            tokens: (int) $response->json('usage.total_tokens', 0),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(array $payload): Response
    {
        if (! $this->apiKey) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->retry(2, 500)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: '.$response->body());
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArguments(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
