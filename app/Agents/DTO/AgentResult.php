<?php

namespace App\Agents\DTO;

/**
 * Output of an agent run.
 */
class AgentResult
{
    /**
     * @param  array<int, array{name:string, arguments:array, result:array}>  $toolCalls
     */
    public function __construct(
        public readonly string $reply,
        public readonly array $toolCalls = [],
        public readonly int $tokens = 0,
        public readonly bool $needsHuman = false,
    ) {}
}
