<?php

namespace App\Services\Llm;

use App\Services\Llm\Contracts\LlmClient;

/**
 * Deterministic client for the 'fake' driver and tests. Returns canned replies
 * and records all calls for assertions. Scripted turns can be queued to drive
 * the agent tool-calling loop in tests.
 */
class FakeLlmClient implements LlmClient
{
    /** @var array<int, array{model:string, messages:array, options:array}> */
    public array $calls = [];

    /** @var array<int, array{model:string, messages:array, tools:array}> */
    public array $toolCalls = [];

    /** @var array<int, LlmTurn> */
    private array $scriptedTurns = [];

    public function __construct(public string $reply = 'Salom! Sizga qanday yordam bera olaman?') {}

    /**
     * Queue the turns chatWithTools() will return, in order.
     */
    public function script(LlmTurn ...$turns): self
    {
        $this->scriptedTurns = array_merge($this->scriptedTurns, $turns);

        return $this;
    }

    public function chat(string $model, array $messages, array $options = []): string
    {
        $this->calls[] = ['model' => $model, 'messages' => $messages, 'options' => $options];

        return $this->reply;
    }

    public function chatWithTools(string $model, array $messages, array $tools, array $options = []): LlmTurn
    {
        $this->toolCalls[] = ['model' => $model, 'messages' => $messages, 'tools' => $tools];

        if ($this->scriptedTurns !== []) {
            return array_shift($this->scriptedTurns);
        }

        return new LlmTurn(content: $this->reply);
    }
}
