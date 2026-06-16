<?php

namespace App\Agents\Tools;

use App\Services\Inventory\InventoryService;

/**
 * Lets the sales agent look up real products. Price/stock/availability MUST
 * come from this tool — the model never invents them (CLAUDE.md rule #1).
 */
class SearchInventoryTool extends AbstractTool
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function name(): string
    {
        return 'search_inventory';
    }

    public function description(): string
    {
        return 'Search the shop\'s real inventory by product name, category or '
            .'brand. Use this to answer ANY question about price, availability '
            .'or stock. Only state facts returned here — never guess.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'What the customer is looking for'],
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

        $results = $this->inventory->semanticSearch($context->store, $query)
            ->map(fn ($product) => [
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => $product->quantity,
                'in_stock' => ! $product->isOutOfStock(),
                'brand' => $product->brand,
            ])
            ->all();

        return ['results' => $results];
    }
}
