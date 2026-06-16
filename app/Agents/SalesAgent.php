<?php

namespace App\Agents;

use App\Agents\DTO\AgentContext;
use App\Agents\DTO\AgentResult;
use App\Agents\Tools\Contracts\Tool;
use App\Agents\Tools\SaveLeadTool;
use App\Agents\Tools\SearchInventoryTool;
use App\Agents\Tools\SearchShopInfoTool;
use App\Agents\Tools\ShareCatalogTool;
use App\Agents\Tools\ToolContext;
use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\LlmToolCall;

/**
 * The customer-facing sales agent. Runs a tool-calling loop: the model can
 * only touch real data through registered tools (search_inventory, save_lead),
 * so it can never invent prices/stock (CLAUDE.md rule #1).
 */
class SalesAgent
{
    private const MAX_ITERATIONS = 5;

    private const DEFAULT_SYSTEM_PROMPT = 'Siz do\'kon uchun yordamchi sotuvchisiz. '
        .'Narx va mavjudlikni faqat search_inventory natijasidan ayting. '
        .'Manzil, telefon, ish vaqti yoki yetkazib berish haqida so\'ralsa, '
        .'search_shop_info dan foydalaning. '
        .'Mijoz mahsulotlarni ko\'rmoqchi yoki buyurtma bermoqchi bo\'lsa, '
        .'get_catalog_link orqali katalog havolasini yuboring. '
        .'Mijoz qiziqsa save_lead bilan ma\'lumotini saqlang. Mijoz tilida javob bering.';

    /** @var array<string, Tool> */
    private array $tools;

    public function __construct(
        private readonly LlmClient $llm,
        SearchInventoryTool $searchInventory,
        SearchShopInfoTool $searchShopInfo,
        SaveLeadTool $saveLead,
        ShareCatalogTool $shareCatalog,
    ) {
        $this->tools = [
            $searchInventory->name() => $searchInventory,
            $searchShopInfo->name() => $searchShopInfo,
            $saveLead->name() => $saveLead,
            $shareCatalog->name() => $shareCatalog,
        ];
    }

    public function handle(AgentContext $context): AgentResult
    {
        $toolContext = new ToolContext(
            store: $context->conversation->store,
            conversation: $context->conversation,
            customer: $context->conversation->customer,
        );

        $messages = $this->initialMessages($context);
        $definitions = array_map(fn (Tool $t) => $t->definition(), array_values($this->tools));

        $executed = [];
        $tokens = 0;

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $turn = $this->llm->chatWithTools(
                config('chatig.llm.models.sales'),
                $messages,
                $definitions,
            );
            $tokens += $turn->tokens;

            if (! $turn->hasToolCalls()) {
                return new AgentResult(
                    reply: (string) ($turn->content ?? ''),
                    toolCalls: $executed,
                    tokens: $tokens,
                );
            }

            // Append the assistant's tool-call message, then each tool result.
            $messages[] = $this->assistantToolCallMessage($turn->toolCalls);

            foreach ($turn->toolCalls as $call) {
                $result = $this->runTool($call->name, $call->arguments, $toolContext);
                $executed[] = ['name' => $call->name, 'arguments' => $call->arguments, 'result' => $result];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call->id,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        // Tool loop didn't converge — hand off to a human.
        return new AgentResult(
            reply: 'Bir lahza, hamkasbim siz bilan bog\'lanadi.',
            toolCalls: $executed,
            tokens: $tokens,
            needsHuman: true,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function initialMessages(AgentContext $context): array
    {
        $messages = [
            ['role' => 'system', 'content' => $context->systemPrompt ?: self::DEFAULT_SYSTEM_PROMPT],
        ];

        foreach ($context->history as $entry) {
            $messages[] = ['role' => $entry['role'], 'content' => $entry['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $context->userMessage];

        return $messages;
    }

    /**
     * @param  array<int, LlmToolCall>  $toolCalls
     * @return array<string, mixed>
     */
    private function assistantToolCallMessage(array $toolCalls): array
    {
        return [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => array_map(fn (LlmToolCall $call) => [
                'id' => $call->id,
                'type' => 'function',
                'function' => [
                    'name' => $call->name,
                    'arguments' => json_encode($call->arguments, JSON_UNESCAPED_UNICODE),
                ],
            ], $toolCalls),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(string $name, array $arguments, ToolContext $context): array
    {
        if (! isset($this->tools[$name])) {
            return ['ok' => false, 'error' => "Unknown tool: {$name}"];
        }

        return $this->tools[$name]->handle($arguments, $context);
    }
}
