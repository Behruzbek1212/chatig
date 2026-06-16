<?php

namespace Tests\Feature;

use App\Models\ShopFact;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopFactTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $store = Store::factory()->create();

        return User::factory()->create(['store_id' => $store->id]);
    }

    public function test_create_shop_fact(): void
    {
        $user = $this->owner();

        $response = $this->actingAs($user)->postJson('/api/v1/shop-facts', [
            'label' => 'Manzil',
            'value' => 'Toshkent, Chilonzor tumani',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Manzil')
            ->assertJsonPath('data.value', 'Toshkent, Chilonzor tumani');

        $this->assertDatabaseHas('shop_facts', [
            'store_id' => $user->store_id,
            'label' => 'Manzil',
        ]);
    }

    public function test_create_requires_label_and_value(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson('/api/v1/shop-facts', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['label', 'value']);
    }

    public function test_list_is_scoped_to_store(): void
    {
        $user = $this->owner();
        ShopFact::factory()->count(2)->create(['store_id' => $user->store_id]);
        ShopFact::factory()->count(3)->create(); // other stores

        $this->actingAs($user)->getJson('/api/v1/shop-facts')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_access_other_stores_fact(): void
    {
        $user = $this->owner();
        $other = ShopFact::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/shop-facts/{$other->id}")->assertNotFound();
    }

    public function test_update_shop_fact(): void
    {
        $user = $this->owner();
        $fact = ShopFact::factory()->create(['store_id' => $user->store_id, 'label' => 'Telefon']);

        $this->actingAs($user)->patchJson("/api/v1/shop-facts/{$fact->id}", ['value' => '+998901234567'])
            ->assertOk()
            ->assertJsonPath('data.value', '+998901234567');
    }

    public function test_delete_shop_fact(): void
    {
        $user = $this->owner();
        $fact = ShopFact::factory()->create(['store_id' => $user->store_id]);

        $this->actingAs($user)->deleteJson("/api/v1/shop-facts/{$fact->id}")->assertOk();
        $this->assertDatabaseMissing('shop_facts', ['id' => $fact->id]);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/shop-facts')->assertUnauthorized();
    }
}
