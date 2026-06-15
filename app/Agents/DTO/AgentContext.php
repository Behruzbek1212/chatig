<?php

namespace App\Agents\DTO;

use App\Models\Conversation;

/**
 * Input to an agent run: the conversation (which carries store + customer),
 * the new inbound customer text, and the prior message history.
 */
class AgentContext
{
    /**
     * @param  array<int, array{role:string, content:string}>  $history
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $userMessage,
        public readonly array $history = [],
        public readonly ?string $systemPrompt = null,
    ) {}
}
