<?php

namespace App\Agents\Tools\Contracts;

use App\Agents\Tools\ToolContext;

/**
 * A function-calling tool the sales agent can invoke. Tools are the ONLY way
 * the AI touches real data (CLAUDE.md rule #1) — the model can never invent
 * facts or perform actions outside of the tools registered here.
 */
interface Tool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON schema for the tool parameters (OpenAI function-calling format).
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Execute the tool. Returns a payload that is fed back to the model.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, ToolContext $context): array;

    /**
     * OpenAI tool definition wrapper.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;
}
