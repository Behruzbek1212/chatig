<?php

namespace Tests\Feature;

use App\Models\AiConfig;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\Ai\PromptGeneratorService;
use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\FakeLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private FakeLlmClient $llm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->llm = new FakeLlmClient('Test javob');
        $this->app->instance(LlmClient::class, $this->llm);
    }

    private function owner(): User
    {
        return User::factory()->create(['store_id' => Store::factory()->create()->id]);
    }

    public function test_generate_returns_prompt_with_guardrails(): void
    {
        $this->actingAs($this->owner())->postJson('/api/v1/ai/prompt/generate', [
            'tone' => 'dostona',
        ])->assertOk()
            ->assertJsonPath('data.generated_prompt', fn ($p) => str_contains($p, PromptGeneratorService::GUARDRAIL_MARKER));
    }

    public function test_test_endpoint_returns_reply(): void
    {
        $this->actingAs($this->owner())->postJson('/api/v1/ai/prompt/test', [
            'prompt' => 'Siz sotuvchisiz',
            'message' => 'Salom',
        ])->assertOk()->assertJsonPath('data.reply', 'Test javob');
    }

    public function test_show_returns_null_when_no_config(): void
    {
        $this->actingAs($this->owner())->getJson('/api/v1/ai/settings')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_update_saves_and_bumps_version(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->putJson('/api/v1/ai/settings', [
            'system_prompt' => 'V1 prompt',
            'mode' => 'suggest',
        ])->assertOk()->assertJsonPath('data.version', 1);

        $this->actingAs($user)->putJson('/api/v1/ai/settings', [
            'system_prompt' => 'V2 prompt',
            'mode' => 'auto',
        ])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.mode', 'auto');

        // Only one active config remains.
        $this->assertSame(1, $user->store->refresh()
            ? AiConfig::where('store_id', $user->store_id)->where('is_active', true)->count()
            : 0);
    }

    public function test_settings_are_store_scoped(): void
    {
        $a = $this->owner();
        $this->actingAs($a)->putJson('/api/v1/ai/settings', ['system_prompt' => 'A', 'mode' => 'suggest'])->assertOk();

        $b = $this->owner();
        $this->actingAs($b)->getJson('/api/v1/ai/settings')->assertOk()->assertJsonPath('data', null);
    }

    public function test_requires_auth(): void
    {
        $this->postJson('/api/v1/ai/prompt/generate', [])->assertUnauthorized();
    }
}
