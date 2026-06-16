<?php

namespace Tests\Feature\MiniApp;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitDataAuthTest extends TestCase
{
    use BuildsInitData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInitData();
    }

    public function test_valid_init_data_is_accepted(): void
    {
        $store = Store::factory()->create();

        $this->getJson(
            "/api/v1/mini-app/stores/{$store->public_id}/store",
            $this->initDataHeader(['start_param' => $store->public_id]),
        )->assertOk()->assertJsonPath('data.name', $store->name);
    }

    public function test_missing_header_is_rejected(): void
    {
        $store = Store::factory()->create();

        $this->getJson("/api/v1/mini-app/stores/{$store->public_id}/store")
            ->assertForbidden();
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $store = Store::factory()->create();
        $initData = $this->initData(['start_param' => $store->public_id]).'&hash=deadbeef';

        $this->getJson(
            "/api/v1/mini-app/stores/{$store->public_id}/store",
            ['X-Telegram-Init-Data' => $initData],
        )->assertForbidden();
    }

    public function test_stale_auth_date_is_rejected(): void
    {
        $store = Store::factory()->create();

        $this->getJson(
            "/api/v1/mini-app/stores/{$store->public_id}/store",
            $this->initDataHeader(['start_param' => $store->public_id, 'auth_date' => time() - 10_000]),
        )->assertForbidden();
    }

    public function test_start_param_mismatch_is_rejected(): void
    {
        $a = Store::factory()->create();
        $b = Store::factory()->create();

        // Authenticated via store A's deep link, but querying store B's path.
        $this->getJson(
            "/api/v1/mini-app/stores/{$b->public_id}/store",
            $this->initDataHeader(['start_param' => $a->public_id]),
        )->assertForbidden();
    }

    public function test_unknown_store_is_404(): void
    {
        $this->getJson(
            '/api/v1/mini-app/stores/01JUNKUNKNOWN00000000000000/store',
            $this->initDataHeader(),
        )->assertNotFound();
    }
}
