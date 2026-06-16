<?php

namespace Tests\Unit;

use App\Agents\DTO\AgentContext;
use App\Agents\SalesAgent;
use App\Agents\Tools\SaveLeadTool;
use App\Agents\Tools\SearchInventoryTool;
use App\Agents\Tools\SearchShopInfoTool;
use App\Agents\Tools\ShareCatalogTool;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Store;
use App\Services\Crm\LeadService;
use App\Services\Inventory\InventoryService;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmToolCall;
use App\Services\Llm\LlmTurn;
use App\Services\ShopFacts\ShopFactService;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAgentTest extends TestCase
{
    use RefreshDatabase;

    private function agent(FakeLlmClient $llm): SalesAgent
    {
        return new SalesAgent(
            $llm,
            new SearchInventoryTool(app(InventoryService::class)),
            new SearchShopInfoTool(app(ShopFactService::class)),
            new SaveLeadTool(new LeadService),
            new ShareCatalogTool,
        );
    }

    public function test_runs_tool_loop_then_returns_final_reply(): void
    {
        $store = Store::factory()->create();
        app(Tenancy::class)->set($store);

        app(InventoryService::class)->createProduct([
            'name' => 'RTX 4060 Gaming', 'price' => 4_200_000, 'quantity' => 2, 'brand' => 'Asus',
        ]);

        $customer = Customer::factory()->create(['store_id' => $store->id, 'channel' => 'instagram']);
        $conversation = Conversation::factory()->create([
            'store_id' => $store->id, 'customer_id' => $customer->id, 'channel' => 'instagram',
        ]);

        $llm = (new FakeLlmClient)->script(
            new LlmTurn(null, [new LlmToolCall('c1', 'search_inventory', ['query' => 'rtx'])], 10),
            new LlmTurn(null, [new LlmToolCall('c2', 'save_lead', ['first_name' => 'Ali', 'phone' => '901234567'])], 8),
            new LlmTurn('RTX 4060 Gaming bor — 4 200 000 so\'m, 2 dona. Saqlab qo\'ydim!', [], 12),
        );

        $result = $this->agent($llm)->handle(new AgentContext(
            conversation: $conversation,
            userMessage: 'rtx 4060 bormi narxi qancha',
        ));

        $this->assertStringContainsString('RTX 4060', $result->reply);
        $this->assertCount(2, $result->toolCalls);
        $this->assertSame(30, $result->tokens);

        // search_inventory ran and returned the real product.
        $this->assertSame('search_inventory', $result->toolCalls[0]['name']);
        $this->assertSame('RTX 4060 Gaming', $result->toolCalls[0]['result']['results'][0]['name']);

        // save_lead created a real lead scoped to the store.
        $this->assertSame(1, $store->leads()->count());
        $this->assertSame('+998901234567', $store->leads()->first()->phone);
    }

    public function test_search_shop_info_tool_runs_against_real_db(): void
    {
        $store = Store::factory()->create();
        app(Tenancy::class)->set($store);

        app(ShopFactService::class)->create($store, ['label' => 'Manzil', 'value' => 'Toshkent, Chilonzor tumani']);

        $customer = Customer::factory()->create(['store_id' => $store->id, 'channel' => 'instagram']);
        $conversation = Conversation::factory()->create([
            'store_id' => $store->id, 'customer_id' => $customer->id, 'channel' => 'instagram',
        ]);

        $llm = (new FakeLlmClient)->script(
            new LlmTurn(null, [new LlmToolCall('c1', 'search_shop_info', ['query' => 'manzil'])], 6),
            new LlmTurn('Biz Toshkent, Chilonzor tumanida joylashganmiz.', [], 9),
        );

        $result = $this->agent($llm)->handle(new AgentContext(
            conversation: $conversation,
            userMessage: 'qayerda joylashgansiz',
        ));

        $this->assertStringContainsString('Chilonzor', $result->reply);
        $this->assertSame('search_shop_info', $result->toolCalls[0]['name']);
        $this->assertSame('Manzil', $result->toolCalls[0]['result']['results'][0]['label']);
        $this->assertSame('Toshkent, Chilonzor tumani', $result->toolCalls[0]['result']['results'][0]['value']);
    }

    public function test_returns_reply_without_tools(): void
    {
        $store = Store::factory()->create();
        $conversation = Conversation::factory()->create(['store_id' => $store->id, 'channel' => 'instagram']);

        $llm = new FakeLlmClient('Assalomu alaykum! Qanday yordam bera olaman?');

        $result = $this->agent($llm)->handle(new AgentContext($conversation, 'salom'));

        $this->assertSame('Assalomu alaykum! Qanday yordam bera olaman?', $result->reply);
        $this->assertCount(0, $result->toolCalls);
    }

    public function test_passes_system_prompt(): void
    {
        $store = Store::factory()->create();
        $conversation = Conversation::factory()->create(['store_id' => $store->id, 'channel' => 'instagram']);
        $llm = new FakeLlmClient('ok');

        $this->agent($llm)->handle(new AgentContext($conversation, 'salom', systemPrompt: 'Maxsus prompt'));

        $this->assertSame('Maxsus prompt', $llm->toolCalls[0]['messages'][0]['content']);
    }
}
