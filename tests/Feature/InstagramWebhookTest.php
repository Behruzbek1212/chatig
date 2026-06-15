<?php

namespace Tests\Feature;

use App\Models\AiConfig;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Store;
use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\FakeLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InstagramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private FakeLlmClient $llm;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('chatig.instagram.app_secret', 'ig-secret');
        config()->set('chatig.instagram.verify_token', 'verify-123');
        config()->set('chatig.instagram.graph_version', 'v23.0');

        $this->llm = new FakeLlmClient('Assalomu alaykum! Bizda bor, yordam beraman.');
        $this->app->instance(LlmClient::class, $this->llm);
    }

    private function connectedChannel(string $mode = 'auto'): Channel
    {
        $store = Store::factory()->create();
        $channel = Channel::factory()->create([
            'store_id' => $store->id,
            'type' => 'instagram',
            'external_id' => 'ig-biz-1',
            'status' => 'connected',
            'access_token' => 'page-token',
        ]);

        AiConfig::create([
            'store_id' => $store->id,
            'system_prompt' => 'Test prompt',
            'mode' => $mode,
            'is_active' => true,
        ]);

        return $channel;
    }

    private function postSigned(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'ig-secret');

        return $this->call('POST', '/api/v1/webhooks/instagram', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function messagePayload(string $text = 'salom', string $mid = 'mid-1'): array
    {
        return [
            'object' => 'instagram',
            'entry' => [[
                'id' => 'ig-biz-1',
                'messaging' => [[
                    'sender' => ['id' => 'customer-99'],
                    'recipient' => ['id' => 'ig-biz-1'],
                    'message' => ['mid' => $mid, 'text' => $text],
                ]],
            ]],
        ];
    }

    public function test_verify_handshake_returns_challenge(): void
    {
        $this->get('/api/v1/webhooks/instagram?hub.mode=subscribe&hub.verify_token=verify-123&hub.challenge=42')
            ->assertOk()
            ->assertSee('42');
    }

    public function test_verify_handshake_rejects_wrong_token(): void
    {
        $this->get('/api/v1/webhooks/instagram?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')
            ->assertForbidden();
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->connectedChannel();

        $this->call('POST', '/api/v1/webhooks/instagram', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($this->messagePayload()))->assertForbidden();
    }

    public function test_auto_mode_stores_messages_and_sends_reply(): void
    {
        Http::fake(['graph.instagram.com/*' => Http::response(['message_id' => 'x'])]);
        $channel = $this->connectedChannel('auto');

        $this->postSigned($this->messagePayload('rtx bormi'))->assertOk();

        $this->assertDatabaseHas('messages', [
            'store_id' => $channel->store_id,
            'role' => 'customer',
            'direction' => 'inbound',
            'content' => 'rtx bormi',
        ]);
        $this->assertDatabaseHas('messages', [
            'store_id' => $channel->store_id,
            'role' => 'ai',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/me/messages'));
    }

    public function test_suggest_mode_does_not_send(): void
    {
        Http::fake(['graph.instagram.com/*' => Http::response([])]);
        $channel = $this->connectedChannel('suggest');

        $this->postSigned($this->messagePayload('salom'))->assertOk();

        $this->assertDatabaseHas('messages', [
            'store_id' => $channel->store_id,
            'role' => 'ai',
            'status' => 'suggested',
        ]);
        Http::assertNothingSent();
    }

    public function test_duplicate_message_id_is_ignored(): void
    {
        Http::fake(['graph.instagram.com/*' => Http::response([])]);
        $this->connectedChannel('auto');

        $this->postSigned($this->messagePayload('salom', 'mid-dup'))->assertOk();
        $this->postSigned($this->messagePayload('salom', 'mid-dup'))->assertOk();

        $this->assertSame(1, Message::where('external_mid', 'mid-dup')->count());
    }

    public function test_unknown_account_is_ignored(): void
    {
        $payload = $this->messagePayload();
        $payload['entry'][0]['id'] = 'unknown-account';
        $payload['entry'][0]['messaging'][0]['recipient']['id'] = 'unknown-account';

        $this->postSigned($payload)->assertOk();
        $this->assertSame(0, Message::count());
    }
}
