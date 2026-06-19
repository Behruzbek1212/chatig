<?php

namespace Tests\Feature\Auth;

use App\Jobs\AnalyzeInstagramProfile;
use App\Models\Channel;
use App\Models\ShopFact;
use App\Models\Store;
use App\Services\Auth\Ai\AiSettingsService;
use App\Services\Auth\Ai\PromptGeneratorService;
use App\Services\Channels\InstagramService;
use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\ShopFacts\ShopFactService;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyzeInstagramProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // graph.instagram.com profile + media snapshot used by fetchProfileInsights().
        // NOTE: order matters — the more specific media + business_discovery
        // patterns must precede the generic /me* fallback.
        Http::fake([
            'graph.instagram.com/*/me/media*' => Http::response([
                'data' => [
                    ['caption' => 'Toshkent bo\'ylab bepul yetkazib berish', 'media_type' => 'IMAGE'],
                    ['caption' => 'Yangi ko\'ylaklar keldi!', 'media_type' => 'IMAGE'],
                ],
            ]),
            // Business Discovery self-lookup that fetchBiography() makes.
            'graph.instagram.com/*business_discovery*' => Http::response([
                'business_discovery' => [
                    'biography' => 'Ayollar kiyimlari · Toshkent · Tel: +998901112233',
                ],
            ]),
            'graph.instagram.com/*/me*' => Http::response([
                'username' => 'moda_shop',
                'name' => 'Moda Boutique',
                'account_type' => 'BUSINESS',
                'followers_count' => 1200,
                'follows_count' => 300,
                'media_count' => 2,
                'profile_picture_url' => 'https://cdn.instagram.test/moda_shop.jpg',
            ]),
        ]);
    }

    private function bindLlm(FakeLlmClient $fake): void
    {
        $this->app->instance(LlmClient::class, $fake);
    }

    public function test_job_generates_prompt_and_embeds_extracted_shop_facts(): void
    {
        $store = Store::factory()->create(['name' => 'Moda Shop', 'business_type' => 'kiyim']);
        $channel = Channel::factory()->create(['store_id' => $store->id, 'type' => 'instagram']);

        // 1st chat() = prompt draft; 2nd chat() = JSON facts.
        $facts = json_encode(['facts' => [
            ['label' => 'Yetkazib berish', 'value' => 'Toshkent bo\'ylab bepul'],
            ['label' => 'Mahsulot', 'value' => 'Ayollar ko\'ylaklari'],
        ]]);
        $this->bindLlm((new FakeLlmClient)->scriptChat('Boshlang\'ich prompt.', $facts));

        $this->app->make(AnalyzeInstagramProfile::class, ['channelId' => $channel->id])
            ->handle(
                $this->app->make(InstagramService::class),
                $this->app->make(PromptGeneratorService::class),
                $this->app->make(AiSettingsService::class),
                $this->app->make(ShopFactService::class),
                $this->app->make(Tenancy::class),
            );

        // Prompt saved.
        $config = (new AiSettingsService)->current($store);
        $this->assertNotNull($config);
        $this->assertStringContainsString('Boshlang\'ich prompt.', $config->system_prompt);

        // New profile insight fields thread into raw_inputs.
        $this->assertSame(300, $config->raw_inputs['follows_count']);
        $this->assertSame('https://cdn.instagram.test/moda_shop.jpg', $config->raw_inputs['profile_picture_url']);
        // Bio was fetched via Business Discovery and recorded.
        $this->assertTrue($config->raw_inputs['has_bio']);

        // Facts persisted (embedding is a no-op on sqlite, but rows exist).
        $stored = ShopFact::where('store_id', $store->id)->orderBy('display_order')->get();
        $this->assertCount(2, $stored);
        $this->assertSame('Yetkazib berish', $stored[0]->label);
        $this->assertSame('Ayollar ko\'ylaklari', $stored[1]->value);

        // Channel marked ready.
        $this->assertSame('ready', $channel->fresh()->meta['ai_setup_status']);
    }

    public function test_job_skips_fact_bootstrap_when_store_already_has_facts(): void
    {
        $store = Store::factory()->create();
        $channel = Channel::factory()->create(['store_id' => $store->id, 'type' => 'instagram']);

        // Owner already added a fact manually — auto-bootstrap must not run.
        ShopFact::factory()->create(['store_id' => $store->id, 'label' => 'Qo\'lda', 'value' => 'mavjud']);

        $facts = json_encode(['facts' => [['label' => 'Auto', 'value' => 'qo\'shilmasligi kerak']]]);
        $this->bindLlm((new FakeLlmClient)->scriptChat('prompt', $facts));

        $this->app->make(AnalyzeInstagramProfile::class, ['channelId' => $channel->id])
            ->handle(
                $this->app->make(InstagramService::class),
                $this->app->make(PromptGeneratorService::class),
                $this->app->make(AiSettingsService::class),
                $this->app->make(ShopFactService::class),
                $this->app->make(Tenancy::class),
            );

        $labels = ShopFact::where('store_id', $store->id)->pluck('label');
        $this->assertContains('Qo\'lda', $labels);
        $this->assertNotContains('Auto', $labels);
    }
}
