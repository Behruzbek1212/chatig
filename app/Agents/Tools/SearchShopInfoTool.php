<?php

namespace App\Agents\Tools;

use App\Services\ShopFacts\ShopFactService;

/**
 * Lets the sales agent look up real shop facts (address, phone, working
 * hours, delivery/return policy, etc.). These MUST come from this tool —
 * the model never invents them (CLAUDE.md rule #1).
 */
class SearchShopInfoTool extends AbstractTool
{
    public function __construct(private readonly ShopFactService $shopFacts) {}

    public function name(): string
    {
        return 'search_shop_info';
    }

    public function description(): string
    {
        return 'Search the shop\'s own facts: address, phone number, working '
            .'hours, delivery terms, return policy, and similar shop-level '
            .'info. Use this for ANY question about the shop itself (not '
            .'products). Only state facts returned here — never guess.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'What the customer wants to know about the shop'],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments, ToolContext $context): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return ['results' => []];
        }

        $results = $this->shopFacts->semanticSearch($context->store, $query)
            ->map(fn ($fact) => [
                'label' => $fact->label,
                'value' => $fact->value,
            ])
            ->all();

        return ['results' => $results];
    }
}
