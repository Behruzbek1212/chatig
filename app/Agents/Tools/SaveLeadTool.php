<?php

namespace App\Agents\Tools;

use App\Services\Crm\LeadService;

/**
 * Lets the sales agent capture a customer's contact details. The description
 * is written so the model calls it as soon as the customer shows buying intent
 * or shares any of name/phone/city — and NEVER invents values.
 */
class SaveLeadTool extends AbstractTool
{
    public function __construct(private readonly LeadService $leads) {}

    public function name(): string
    {
        return 'save_lead';
    }

    public function description(): string
    {
        return 'Save or update the customer\'s contact details when they express '
            .'intent to buy or provide their name, phone, or city. Call this as '
            .'soon as you learn any of these fields; you may call it again later '
            .'to add more. Never guess values — pass only what the customer '
            .'actually stated.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'first_name' => ['type' => 'string', 'description' => 'Customer first name (ism)'],
                'last_name' => ['type' => 'string', 'description' => 'Customer last name (familiya)'],
                'city' => ['type' => 'string', 'description' => 'Customer city (shahar)'],
                'phone' => ['type' => 'string', 'description' => 'Phone number, ideally +998XXXXXXXXX'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments, ToolContext $context): array
    {
        $hasName = ! empty($arguments['first_name']);
        $hasPhone = ! empty($arguments['phone']);

        if (! $hasName && ! $hasPhone) {
            return [
                'ok' => false,
                'message' => 'Kamida ism yoki telefon raqami kerak.',
            ];
        }

        $lead = $this->leads->createOrUpdate($context->store, [
            'first_name' => $arguments['first_name'] ?? null,
            'last_name' => $arguments['last_name'] ?? null,
            'city' => $arguments['city'] ?? null,
            'phone' => $arguments['phone'] ?? null,
            'customer_id' => $context->customer?->id,
            'conversation_id' => $context->conversation?->id,
            'source' => $this->resolveSource($context),
        ]);

        return [
            'ok' => true,
            'lead_id' => $lead->id,
            'message' => 'Mijoz ma\'lumotlari saqlandi.',
        ];
    }

    private function resolveSource(ToolContext $context): string
    {
        if ($context->conversation) {
            return $context->conversation->channel;
        }

        if ($context->customer) {
            return $context->customer->channel;
        }

        return 'manual';
    }
}
