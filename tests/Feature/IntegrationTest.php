<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('chatig.instagram', array_merge(config('chatig.instagram'), [
            'app_id' => 'ig-app-123',
            'app_secret' => 'ig-secret',
            'redirect_uri' => 'https://api.chatig.test/api/v1/integrations/instagram/callback',
            'graph_version' => 'v23.0',
        ]));
        config()->set('chatig.spa_url', 'https://app.chatig.test');
    }

    private function owner(): User
    {
        return User::factory()->create(['store_id' => Store::factory()->create()->id]);
    }

    public function test_connect_url_requires_instagram_credentials(): void
    {
        config()->set('chatig.instagram.app_id', '');
        config()->set('chatig.instagram.redirect_uri', '');

        $this->actingAs($this->owner())
            ->getJson('/api/v1/integrations/instagram/connect-url')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Instagram integratsiyasi sozlanmagan. INSTAGRAM_APP_ID va INSTAGRAM_REDIRECT_URI ni .env faylida to\'ldiring.');
    }

    public function test_connect_url_uses_instagram_authorize_with_business_scopes(): void
    {
        $url = $this->actingAs($this->owner())->getJson('/api/v1/integrations/instagram/connect-url')
            ->assertOk()
            ->json('data.url');

        $this->assertStringStartsWith('https://www.instagram.com/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=ig-app-123', $url);
        $this->assertStringContainsString('instagram_business_manage_messages', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function test_callback_connects_instagram_account_via_instagram_login(): void
    {
        $store = Store::factory()->create();
        $state = Crypt::encryptString((string) $store->id);

        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-tok', 'user_id' => 17841400000000000]),
            'graph.instagram.com/*/access_token*' => Http::response(['access_token' => 'long-tok', 'token_type' => 'bearer', 'expires_in' => 5184000]),
            'graph.instagram.com/*/me*' => Http::response(['user_id' => '17841400000000000', 'username' => 'texno_shop']),
            'graph.instagram.com/*/me/subscribed_apps*' => Http::response(['success' => true]),
        ]);

        $this->get('/api/v1/integrations/instagram/callback?code=abc&state='.urlencode($state))
            ->assertRedirect('https://app.chatig.test/integrations?instagram=connected');

        $this->assertDatabaseHas('channels', [
            'store_id' => $store->id,
            'type' => 'instagram',
            'external_id' => '17841400000000000',
            'username' => 'texno_shop',
            'status' => 'connected',
        ]);
    }

    public function test_callback_without_code_redirects_error(): void
    {
        $this->get('/api/v1/integrations/instagram/callback?error=denied')
            ->assertRedirect('https://app.chatig.test/integrations?instagram=error');
    }

    public function test_callback_redirects_error_when_token_exchange_fails(): void
    {
        $store = Store::factory()->create();
        $state = Crypt::encryptString((string) $store->id);

        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['error' => 'invalid'], 400),
        ]);

        $this->get('/api/v1/integrations/instagram/callback?code=abc&state='.urlencode($state))
            ->assertRedirect('https://app.chatig.test/integrations?instagram=error');
    }

    public function test_index_is_store_scoped_and_hides_token(): void
    {
        $user = $this->owner();
        Channel::factory()->create(['store_id' => $user->store_id]);
        Channel::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/integrations')->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('access_token', $response->getContent());
    }

    public function test_destroy_disconnects_channel(): void
    {
        $user = $this->owner();
        $channel = Channel::factory()->create(['store_id' => $user->store_id]);

        $this->actingAs($user)->deleteJson("/api/v1/integrations/{$channel->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'disconnected');
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/integrations')->assertUnauthorized();
    }
}
