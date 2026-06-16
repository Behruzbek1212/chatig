<?php

namespace App\Agents\Tools;

/**
 * Returns the deep link to this shop's Telegram Mini App catalog so the sales
 * agent can redirect Instagram customers there to browse products with photos,
 * prices and stock, and place an order. The store comes from the ToolContext —
 * never from model input.
 */
class ShareCatalogTool extends AbstractTool
{
    public function name(): string
    {
        return 'get_catalog_link';
    }

    public function description(): string
    {
        return 'Get the link to this shop\'s product catalog (Telegram Mini App). '
            .'Use it whenever the customer wants to browse products, see the full '
            .'catalog, view photos/prices, or place an order. Send the returned URL '
            .'to the customer.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments, ToolContext $context): array
    {
        $username = config('chatig.telegram.bot_username');
        $publicId = $context->store->public_id;

        if (! $username || ! $publicId) {
            return ['ok' => false, 'message' => 'Katalog hozircha mavjud emas.'];
        }

        return [
            'ok' => true,
            'url' => "https://t.me/{$username}/app?startapp={$publicId}",
        ];
    }
}
