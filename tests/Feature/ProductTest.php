<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $store = Store::factory()->create();

        return User::factory()->create(['store_id' => $store->id]);
    }

    public function test_create_product_with_images(): void
    {
        Storage::fake('public');
        $user = $this->owner();

        $response = $this->actingAs($user)->postJson('/api/v1/products', [
            'name' => 'RTX 4060',
            'price' => 4_200_000,
            'quantity' => 3,
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'RTX 4060')
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonCount(2, 'data.images');

        $this->assertTrue($response->json('data.images.0.is_primary'));
    }

    public function test_rejects_more_than_ten_images(): void
    {
        Storage::fake('public');
        $user = $this->owner();

        $images = collect(range(1, 11))->map(fn ($i) => UploadedFile::fake()->image("$i.jpg"))->all();

        $this->actingAs($user)->postJson('/api/v1/products', [
            'name' => 'X',
            'price' => 100,
            'images' => $images,
        ])->assertStatus(422)->assertJsonValidationErrors('images');
    }

    public function test_list_is_scoped_to_store(): void
    {
        $user = $this->owner();
        Product::factory()->count(2)->create(['store_id' => $user->store_id]);
        Product::factory()->count(3)->create(); // other stores

        $this->actingAs($user)->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_access_other_stores_product(): void
    {
        $user = $this->owner();
        $other = Product::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/products/{$other->id}")->assertNotFound();
    }

    public function test_update_product(): void
    {
        $user = $this->owner();
        $product = Product::factory()->create(['store_id' => $user->store_id]);

        $this->actingAs($user)->patchJson("/api/v1/products/{$product->id}", ['name' => 'Yangi nom'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Yangi nom');
    }

    public function test_delete_product(): void
    {
        $user = $this->owner();
        $product = Product::factory()->create(['store_id' => $user->store_id]);

        $this->actingAs($user)->deleteJson("/api/v1/products/{$product->id}")->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_movements_endpoint(): void
    {
        $user = $this->owner();
        $product = $this->actingAs($user)->postJson('/api/v1/products', [
            'name' => 'X', 'price' => 100, 'quantity' => 5,
        ])->json('data.id');

        $this->actingAs($user)->getJson("/api/v1/products/{$product}/movements")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }
}
