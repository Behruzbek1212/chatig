<?php

namespace Tests\Feature\Auth;

use App\Models\Store;
use App\Models\User;
use App\Services\Sms\Contracts\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsSender;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
    }

    public function test_register_creates_store_and_unverified_user_and_sends_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '901234567',
            'company_name' => 'Texno Do\'kon',
            'business_type' => 'elektronika',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('stores', ['name' => 'Texno Do\'kon', 'business_type' => 'elektronika']);
        $this->assertDatabaseHas('users', ['phone' => '+998901234567', 'phone_verified_at' => null]);
        $this->assertNotNull($this->sms->lastCodeFor('+998901234567'));
    }

    public function test_register_validates_business_type(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'phone' => '901234567',
            'company_name' => 'X',
            'business_type' => 'invalid',
        ])->assertStatus(422)->assertJsonValidationErrors('business_type');
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+998901234567']);

        $this->postJson('/api/v1/auth/register', [
            'phone' => '901234567',
            'company_name' => 'X',
            'business_type' => 'elektronika',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_verify_register_returns_token_and_verifies_user(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'phone' => '901234567',
            'company_name' => 'Texno',
            'business_type' => 'elektronika',
        ])->assertCreated();

        $code = $this->sms->lastCodeFor('+998901234567');

        $response = $this->postJson('/api/v1/auth/register/verify', [
            'phone' => '901234567',
            'code' => $code,
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['token', 'user' => ['id', 'phone', 'store']]]);
        $this->assertDatabaseHas('users', ['phone' => '+998901234567']);
        $this->assertNotNull(User::where('phone', '+998901234567')->first()->phone_verified_at);
    }

    public function test_verify_register_rejects_wrong_code(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'phone' => '901234567',
            'company_name' => 'Texno',
            'business_type' => 'elektronika',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/register/verify', [
            'phone' => '901234567',
            'code' => '000000',
        ])->assertStatus(422);
    }

    public function test_login_sends_otp_for_existing_user(): void
    {
        $store = Store::factory()->create();
        User::factory()->create(['store_id' => $store->id, 'phone' => '+998901234567']);

        $this->postJson('/api/v1/auth/login', ['phone' => '901234567'])->assertOk();
        $this->assertNotNull($this->sms->lastCodeFor('+998901234567'));
    }

    public function test_login_does_not_send_for_unknown_user_but_returns_ok(): void
    {
        $this->postJson('/api/v1/auth/login', ['phone' => '901234567'])->assertOk();
        $this->assertNull($this->sms->lastCodeFor('+998901234567'));
    }

    public function test_verify_login_returns_token(): void
    {
        $store = Store::factory()->create();
        User::factory()->create(['store_id' => $store->id, 'phone' => '+998901234567']);

        $this->postJson('/api/v1/auth/login', ['phone' => '901234567'])->assertOk();
        $code = $this->sms->lastCodeFor('+998901234567');

        $this->postJson('/api/v1/auth/login/verify', [
            'phone' => '901234567',
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.phone', $user->phone);
    }
}
