<?php

namespace App\Services\Llm\Contracts;

use App\Services\Llm\LlmTurn;

interface LlmClient
{
    /**
     * Send a chat completion request and return the assistant's text reply.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(string $model, array $messages, array $options = []): string;

    /**
     * Send a chat completion request with tools and return a structured turn
     * (assistant text and/or tool calls) for the agent loop.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools  OpenAI tool definitions
     * @param  array<string, mixed>  $options
     */
    public function chatWithTools(string $model, array $messages, array $tools, array $options = []): LlmTurn;
}
