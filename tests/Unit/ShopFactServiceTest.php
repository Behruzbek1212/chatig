<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Services\ShopFacts\ShopFactService;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopFactServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShopFactService $service;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        app(Tenancy::class)->set($this->store);
        $this->service = app(ShopFactService::class);
    }

    public function test_create_shop_fact(): void
    {
        $fact = $this->service->create($this->store, ['label' => 'Manzil', 'value' => 'Toshkent']);

        $this->assertSame('Manzil', $fact->label);
        $this->assertSame('Toshkent', $fact->value);
        $this->assertSame($this->store->id, $fact->store_id);
    }

    public function test_update_shop_fact(): void
    {
        $fact = $this->service->create($this->store, ['label' => 'Telefon', 'value' => 'eski']);

        $updated = $this->service->update($fact, ['value' => '+998901234567']);

        $this->assertSame('+998901234567', $updated->value);
        $this->assertSame('Telefon', $updated->label);
    }

    public function test_delete_shop_fact(): void
    {
        $fact = $this->service->create($this->store, ['label' => 'Telefon', 'value' => '901234567']);

        $this->service->delete($fact);

        $this->assertDatabaseMissing('shop_facts', ['id' => $fact->id]);
    }

    public function test_list_orders_by_display_order(): void
    {
        $this->service->create($this->store, ['label' => 'B', 'value' => 'b', 'display_order' => 2]);
        $this->service->create($this->store, ['label' => 'A', 'value' => 'a', 'display_order' => 1]);

        $labels = $this->service->list($this->store)->pluck('label')->all();

        $this->assertSame(['A', 'B'], $labels);
    }

    public function test_keyword_search_matches_label_and_value(): void
    {
        $this->service->create($this->store, ['label' => 'Manzil', 'value' => 'Toshkent, Chilonzor']);
        $this->service->create($this->store, ['label' => 'Telefon', 'value' => '901234567']);

        $this->assertCount(1, $this->service->keywordSearch($this->store, 'Chilonzor'));
        $this->assertCount(1, $this->service->keywordSearch($this->store, 'Manzil'));
    }

    public function test_semantic_search_falls_back_to_keyword_on_sqlite(): void
    {
        $this->service->create($this->store, ['label' => 'Manzil', 'value' => 'Toshkent, Chilonzor']);

        // sqlite has no pgvector — semanticSearch must transparently degrade
        // to keyword search instead of erroring.
        $results = $this->service->semanticSearch($this->store, 'Chilonzor');

        $this->assertCount(1, $results);
        $this->assertSame('Manzil', $results->first()->label);
    }
}
