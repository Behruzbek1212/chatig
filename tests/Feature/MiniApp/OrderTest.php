<?php

namespace Tests\Feature\MiniApp;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use BuildsInitData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInitData();
    }

    private function order(Store $store, array $payload, array $user = ['id' => 555001, 'first_name' => 'Vali'])
    {
        return $this->postJson(
            "/api/v1/mini-app/stores/{$store->public_id}/orders",
            $payload,
            $this->initDataHeader(['start_param' => $store->public_id, 'user' => $user]),
        );
    }

    public function test_creates_order_with_db_price_and_decrements_stock(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $store->id, 'status' => 'active', 'quantity' => 10, 'price' => 100_000,
        ]);

        $res = $this->order($store, [
            // bogus client price must be ignored — only product_id + quantity matter
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'price' => 1]],
            'customer_name' => 'Vali Aliyev',
            'customer_phone' => '+998901112233',
            'customer_address' => 'Toshkent',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.total', 300_000)
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.items.0.unit_price', 100_000);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 7]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'out', 'qty_change' => -3]);
        $this->assertDatabaseHas('customers', ['store_id' => $store->id, 'channel' => 'telegram', 'external_id' => '555001']);
        $this->assertDatabaseHas('leads', ['store_id' => $store->id, 'source' => 'telegram', 'phone' => '+998901112233']);
    }

    public function test_over_quantity_is_rejected_and_stock_unchanged(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id, 'status' => 'active', 'quantity' => 2]);

        $this->order($store, [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'customer_name' => 'Vali',
            'customer_phone' => '+998901112233',
        ])->assertStatus(422);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cross_store_product_in_cart_is_rejected(): void
    {
        $store = Store::factory()->create();
        $other = Store::factory()->create();
        $foreign = Product::factory()->create(['store_id' => $other->id, 'status' => 'active', 'quantity' => 5]);

        $this->order($store, [
            'items' => [['product_id' => $foreign->id, 'quantity' => 1]],
            'customer_name' => 'Vali',
            'customer_phone' => '+998901112233',
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_validation_requires_items_and_contact(): void
    {
        $store = Store::factory()->create();

        $this->order($store, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items', 'customer_name', 'customer_phone']);
    }
}
