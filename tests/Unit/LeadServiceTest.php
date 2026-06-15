<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Store;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadService $service;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeadService;
        $this->store = Store::factory()->create();
    }

    public function test_creates_lead_and_normalizes_phone(): void
    {
        $lead = $this->service->createOrUpdate($this->store, [
            'first_name' => 'Ali',
            'phone' => '901234567',
            'source' => 'telegram',
        ]);

        $this->assertSame('+998901234567', $lead->phone);
        $this->assertSame('telegram', $lead->source);
    }

    public function test_dedups_by_phone_within_store(): void
    {
        $this->service->createOrUpdate($this->store, ['first_name' => 'Ali', 'phone' => '+998901234567']);
        $this->service->createOrUpdate($this->store, ['city' => 'Tashkent', 'phone' => '901234567']);

        $this->assertSame(1, $this->store->leads()->count());
        $lead = $this->store->leads()->first();
        $this->assertSame('Ali', $lead->first_name);
        $this->assertSame('Tashkent', $lead->city);
    }

    public function test_partial_collection_merges_fields_by_conversation(): void
    {
        // Same conversation id ties updates together even without phone.
        $conv = Conversation::create([
            'store_id' => $this->store->id,
            'channel' => 'telegram',
        ]);

        $a = $this->service->createOrUpdate($this->store, ['first_name' => 'Vali', 'conversation_id' => $conv->id]);
        $b = $this->service->createOrUpdate($this->store, ['city' => 'Andijon', 'conversation_id' => $conv->id]);

        $this->assertSame($a->id, $b->id);
        $this->assertSame('Vali', $b->first_name);
        $this->assertSame('Andijon', $b->city);
    }

    public function test_does_not_leak_across_stores(): void
    {
        $other = Store::factory()->create();
        $this->service->createOrUpdate($this->store, ['first_name' => 'Ali', 'phone' => '+998901234567']);
        $this->service->createOrUpdate($other, ['first_name' => 'Bek', 'phone' => '+998901234567']);

        $this->assertSame(1, $this->store->leads()->count());
        $this->assertSame(1, $other->leads()->count());
    }
}
