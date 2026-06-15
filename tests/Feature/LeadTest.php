<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['store_id' => Store::factory()->create()->id]);
    }

    public function test_index_is_store_scoped(): void
    {
        $user = $this->owner();
        Lead::factory()->count(2)->create(['store_id' => $user->store_id]);
        Lead::factory()->count(3)->create();

        $this->actingAs($user)->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_search_by_phone(): void
    {
        $user = $this->owner();
        Lead::factory()->create(['store_id' => $user->store_id, 'phone' => '+998901112233']);
        Lead::factory()->create(['store_id' => $user->store_id, 'phone' => '+998905556677']);

        $this->actingAs($user)->getJson('/api/v1/leads?q=111')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_update_status_and_notes(): void
    {
        $user = $this->owner();
        $lead = Lead::factory()->create(['store_id' => $user->store_id]);

        $this->actingAs($user)->patchJson("/api/v1/leads/{$lead->id}", [
            'status' => 'contacted',
            'notes' => 'Bog\'lanildi',
        ])->assertOk()->assertJsonPath('data.status', 'contacted');
    }

    public function test_cannot_access_other_store_lead(): void
    {
        $user = $this->owner();
        $other = Lead::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/leads/{$other->id}")->assertNotFound();
    }
}
