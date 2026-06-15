<?php

namespace Tests\Unit;

use App\Agents\Tools\SaveLeadTool;
use App\Agents\Tools\ToolContext;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Store;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveLeadToolTest extends TestCase
{
    use RefreshDatabase;

    private function tool(): SaveLeadTool
    {
        return new SaveLeadTool(new LeadService);
    }

    public function test_definition_has_function_shape(): void
    {
        $def = $this->tool()->definition();

        $this->assertSame('function', $def['type']);
        $this->assertSame('save_lead', $def['function']['name']);
        $this->assertArrayHasKey('phone', $def['function']['parameters']['properties']);
    }

    public function test_saves_lead_scoped_to_conversation_store(): void
    {
        $store = Store::factory()->create();
        $customer = Customer::factory()->create(['store_id' => $store->id, 'channel' => 'telegram']);
        $conversation = Conversation::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'channel' => 'telegram',
        ]);

        $context = new ToolContext($store, $conversation, $customer);

        $result = $this->tool()->handle([
            'first_name' => 'Ali',
            'city' => 'Tashkent',
            'phone' => '901234567',
        ], $context);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $store->leads()->count());
        $lead = $store->leads()->first();
        $this->assertSame('+998901234567', $lead->phone);
        $this->assertSame('telegram', $lead->source);
        $this->assertSame($conversation->id, $lead->conversation_id);
    }

    public function test_rejects_when_no_name_or_phone(): void
    {
        $store = Store::factory()->create();
        $result = $this->tool()->handle(['city' => 'Tashkent'], new ToolContext($store));

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $store->leads()->count());
    }

    public function test_repeated_calls_update_same_lead(): void
    {
        $store = Store::factory()->create();
        $conversation = Conversation::factory()->create(['store_id' => $store->id, 'channel' => 'telegram']);
        $context = new ToolContext($store, $conversation);

        $this->tool()->handle(['first_name' => 'Ali'], $context);
        $this->tool()->handle(['phone' => '901234567'], $context);

        $this->assertSame(1, $store->leads()->count());
        $this->assertSame('+998901234567', $store->leads()->first()->phone);
    }
}
