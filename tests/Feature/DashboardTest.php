<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_counts_checklist_and_subscription(): void
    {
        $store = Store::factory()->create(['trial_ends_at' => now()->addDays(10)]);
        $user = User::factory()->create(['store_id' => $store->id]);

        Product::factory()->count(2)->create(['store_id' => $store->id]);
        Lead::factory()->create(['store_id' => $store->id]);
        Channel::factory()->create(['store_id' => $store->id, 'type' => 'instagram', 'status' => 'connected']);

        $this->actingAs($user)->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.today.products_total', 2)
            ->assertJsonPath('data.today.new_leads', 1)
            ->assertJsonPath('data.checklist.instagram_connected', true)
            ->assertJsonPath('data.checklist.has_products', true)
            ->assertJsonPath('data.checklist.ai_configured', false)
            ->assertJsonPath('data.subscription.on_trial', true);
    }

    public function test_summary_is_store_scoped(): void
    {
        $user = User::factory()->create(['store_id' => Store::factory()->create()->id]);
        Product::factory()->count(5)->create(); // other stores

        $this->actingAs($user)->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.today.products_total', 0);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }
}
