<?php

namespace App\Services\Llm;

class LlmTurn
{
    /**
     * @param  array<int, LlmToolCall>  $toolCalls
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls = [],
        public readonly int $tokens = 0,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
