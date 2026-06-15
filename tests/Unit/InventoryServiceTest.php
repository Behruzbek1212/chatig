<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Services\Inventory\InventoryService;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        app(Tenancy::class)->set($this->store);
        $this->service = app(InventoryService::class);
    }

    public function test_create_product_records_initial_stock_movement(): void
    {
        $product = $this->service->createProduct([
            'name' => 'RTX 4060',
            'price' => 4_200_000,
            'quantity' => 3,
        ]);

        $this->assertSame(3, $product->quantity);
        $this->assertSame('active', $product->status);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'initial',
            'qty_change' => 3,
        ]);
    }

    public function test_zero_quantity_product_is_out_of_stock(): void
    {
        $product = $this->service->createProduct([
            'name' => 'Empty',
            'price' => 100,
            'quantity' => 0,
        ]);

        $this->assertSame(0, $product->quantity);
        $this->assertSame('out_of_stock', $product->status);
    }

    public function test_adjust_stock_appends_movement_and_updates_quantity(): void
    {
        $product = $this->service->createProduct(['name' => 'X', 'price' => 100, 'quantity' => 5]);

        $this->service->adjustStock($product, 'out', -2, 'Sotildi');

        $this->assertSame(3, $product->refresh()->quantity);
        $this->assertSame(2, $product->movements()->count());
    }

    public function test_quantity_never_negative_status_flips(): void
    {
        $product = $this->service->createProduct(['name' => 'X', 'price' => 100, 'quantity' => 1]);
        $this->service->adjustStock($product, 'out', -1, 'Sotildi');

        $this->assertSame(0, $product->refresh()->quantity);
        $this->assertSame('out_of_stock', $product->status);
    }

    public function test_search_matches_name_and_brand(): void
    {
        $this->service->createProduct(['name' => 'RTX 4060 Gaming', 'price' => 100, 'quantity' => 2, 'brand' => 'Asus']);
        $this->service->createProduct(['name' => 'Keyboard', 'price' => 50, 'quantity' => 10, 'brand' => 'Logitech']);

        $this->assertCount(1, $this->service->search($this->store, 'rtx'));
        $this->assertCount(1, $this->service->search($this->store, 'Asus'));
    }

    public function test_update_product_cannot_change_quantity(): void
    {
        $product = $this->service->createProduct(['name' => 'X', 'price' => 100, 'quantity' => 5]);

        $this->service->updateProduct($product, ['name' => 'Y', 'quantity' => 999]);

        $this->assertSame(5, $product->refresh()->quantity);
        $this->assertSame('Y', $product->name);
    }
}
