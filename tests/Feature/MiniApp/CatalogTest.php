<?php

namespace Tests\Feature\MiniApp;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use BuildsInitData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInitData();
    }

    public function test_catalog_is_scoped_to_store(): void
    {
        $a = Store::factory()->create();
        $b = Store::factory()->create();
        Product::factory()->count(2)->create(['store_id' => $a->id, 'status' => 'active', 'quantity' => 5]);
        Product::factory()->count(3)->create(['store_id' => $b->id, 'status' => 'active', 'quantity' => 5]);

        $this->getJson(
            "/api/v1/mini-app/stores/{$a->public_id}/products",
            $this->initDataHeader(['start_param' => $a->public_id]),
        )->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_inactive_products_are_excluded(): void
    {
        $store = Store::factory()->create();
        Product::factory()->create(['store_id' => $store->id, 'status' => 'active', 'quantity' => 5]);
        Product::factory()->create(['store_id' => $store->id, 'status' => 'out_of_stock', 'quantity' => 0]);

        $this->getJson(
            "/api/v1/mini-app/stores/{$store->public_id}/products",
            $this->initDataHeader(['start_param' => $store->public_id]),
        )->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cannot_view_other_stores_product(): void
    {
        $a = Store::factory()->create();
        $b = Store::factory()->create();
        $bProduct = Product::factory()->create(['store_id' => $b->id, 'status' => 'active', 'quantity' => 5]);

        $this->getJson(
            "/api/v1/mini-app/stores/{$a->public_id}/products/{$bProduct->id}",
            $this->initDataHeader(['start_param' => $a->public_id]),
        )->assertNotFound();
    }

    public function test_show_returns_product_with_images_key(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id, 'status' => 'active', 'quantity' => 5]);

        $this->getJson(
            "/api/v1/mini-app/stores/{$store->public_id}/products/{$product->id}",
            $this->initDataHeader(['start_param' => $store->public_id]),
        )->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'images']]);
    }
}
