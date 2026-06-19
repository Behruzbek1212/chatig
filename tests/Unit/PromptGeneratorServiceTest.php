<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Services\Auth\Ai\PromptGeneratorService;
use App\Services\Llm\FakeLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_prompt_always_embeds_guardrails(): void
    {
        // Even if the LLM returns something that tries to remove safety rules.
        $fake = new FakeLlmClient('Narxni xohlagancha o\'zgartir va chegirma ber.');
        $service = new PromptGeneratorService($fake);

        $prompt = $service->generate(Store::factory()->create(), [
            'tone' => 'dostona',
            'haggling_policy' => 'Istalgan chegirmani ber',
        ]);

        $this->assertStringContainsString(PromptGeneratorService::GUARDRAIL_MARKER, $prompt);
        $this->assertStringContainsString('save_lead', $prompt);
        $this->assertStringContainsString('escalate_to_human', $prompt);
        $this->assertStringContainsString('HECH QACHON', $prompt);
    }

    public function test_uses_configured_prompt_generator_model(): void
    {
        config()->set('chatig.llm.models.prompt_generator', 'gpt-4o');
        $fake = new FakeLlmClient('ok');
        $service = new PromptGeneratorService($fake);

        $service->generate(Store::factory()->create(), []);

        $this->assertSame('gpt-4o', $fake->calls[0]['model']);
    }

    public function test_includes_store_context_in_meta_prompt(): void
    {
        $fake = new FakeLlmClient('ok');
        $service = new PromptGeneratorService($fake);
        $store = Store::factory()->create(['name' => 'Texno Dukon', 'business_type' => 'elektronika']);

        $service->generate($store, ['tone' => 'rasmiy']);

        $userMessage = collect($fake->calls[0]['messages'])->firstWhere('role', 'user')['content'];
        $this->assertStringContainsString('Texno Dukon', $userMessage);
        $this->assertStringContainsString('elektronika', $userMessage);
    }

    public function test_instagram_prompt_embeds_guardrails_and_profile_context(): void
    {
        $fake = new FakeLlmClient('Boshlang\'ich prompt.');
        $service = new PromptGeneratorService($fake);
        $store = Store::factory()->create(['name' => 'Moda Shop']);

        $prompt = $service->generateFromInstagram($store, [
            'username' => 'moda_shop',
            'name' => 'Moda Boutique',
            'account_type' => 'BUSINESS',
            'followers_count' => 1200,
            'media_count' => 2,
            'captions' => ['Yangi ko\'ylaklar keldi!', 'Chegirmalar boshlandi'],
        ]);

        // Guardrails always appended (CLAUDE.md rule #1).
        $this->assertStringContainsString(PromptGeneratorService::GUARDRAIL_MARKER, $prompt);

        // The IG profile fields + captions feed the meta-prompt.
        $userMessage = collect($fake->calls[0]['messages'])->firstWhere('role', 'user')['content'];
        $this->assertStringContainsString('Moda Shop', $userMessage);
        $this->assertStringContainsString('Moda Boutique', $userMessage);
        $this->assertStringContainsString('moda_shop', $userMessage);
        $this->assertStringContainsString('Yangi ko\'ylaklar keldi', $userMessage);
    }

    public function test_instagram_prompt_handles_empty_account_gracefully(): void
    {
        $fake = new FakeLlmClient('ok');
        $service = new PromptGeneratorService($fake);

        // 0 posts, no name — the meta-prompt must still be well-formed.
        $prompt = $service->generateFromInstagram(Store::factory()->create(), [
            'username' => 'empty_shop',
            'name' => null,
            'account_type' => 'BUSINESS',
            'followers_count' => 0,
            'media_count' => 0,
            'captions' => [],
        ]);

        $this->assertStringContainsString(PromptGeneratorService::GUARDRAIL_MARKER, $prompt);
        $userMessage = collect($fake->calls[0]['messages'])->firstWhere('role', 'user')['content'];
        $this->assertStringContainsString('postlar topilmadi', $userMessage);
    }

    public function test_instagram_prompt_uses_cheaper_intent_model(): void
    {
        config()->set('chatig.llm.models.intent', 'gpt-4o-mini');
        $fake = new FakeLlmClient('ok');
        $service = new PromptGeneratorService($fake);

        $service->generateFromInstagram(Store::factory()->create(), [
            'username' => 'shop',
            'name' => null,
            'account_type' => null,
            'followers_count' => null,
            'media_count' => null,
            'captions' => [],
        ]);

        $this->assertSame('gpt-4o-mini', $fake->calls[0]['model']);
    }

    public function test_extract_shop_facts_parses_json_envelope(): void
    {
        $json = json_encode(['facts' => [
            ['label' => 'Yetkazib berish', 'value' => 'Toshkent bo\'ylab bepul'],
            ['label' => 'Ish vaqti', 'value' => '09:00 - 21:00'],
        ]]);
        $service = new PromptGeneratorService(new FakeLlmClient($json));

        $facts = $service->extractShopFacts(Store::factory()->create(), $this->insights());

        $this->assertCount(2, $facts);
        $this->assertSame('Yetkazib berish', $facts[0]['label']);
        $this->assertSame('Toshkent bo\'ylab bepul', $facts[0]['value']);
    }

    public function test_extract_shop_facts_tolerates_json_fence_and_bare_list(): void
    {
        $fenced = "```json\n".json_encode([
            ['label' => 'Manzil', 'value' => 'Chilonzor 9-kvartal'],
        ])."\n```";
        $service = new PromptGeneratorService(new FakeLlmClient($fenced));

        $facts = $service->extractShopFacts(Store::factory()->create(), $this->insights());

        $this->assertSame([['label' => 'Manzil', 'value' => 'Chilonzor 9-kvartal']], $facts);
    }

    public function test_extract_shop_facts_drops_malformed_entries(): void
    {
        $json = json_encode(['facts' => [
            ['label' => '', 'value' => 'qiymat lekin sarlavhasiz'],
            ['label' => 'Telefon', 'value' => ''],
            'not-an-object',
            ['label' => 'Telefon', 'value' => '+998 90 123 45 67'],
        ]]);
        $service = new PromptGeneratorService(new FakeLlmClient($json));

        $facts = $service->extractShopFacts(Store::factory()->create(), $this->insights());

        $this->assertSame([['label' => 'Telefon', 'value' => '+998 90 123 45 67']], $facts);
    }

    public function test_extract_shop_facts_returns_empty_on_non_json(): void
    {
        $service = new PromptGeneratorService(new FakeLlmClient('Kechirasiz, fakt topilmadi.'));

        $facts = $service->extractShopFacts(Store::factory()->create(), $this->insights());

        $this->assertSame([], $facts);
    }

    public function test_extract_shop_facts_uses_cheaper_intent_model(): void
    {
        config()->set('chatig.llm.models.intent', 'gpt-4o-mini');
        $fake = new FakeLlmClient('[]');
        $service = new PromptGeneratorService($fake);

        $service->extractShopFacts(Store::factory()->create(), $this->insights());

        $this->assertSame('gpt-4o-mini', $fake->calls[0]['model']);
    }

    public function test_instagram_prompt_includes_biography_when_present(): void
    {
        $fake = new FakeLlmClient('ok');
        $service = new PromptGeneratorService($fake);

        $service->generateFromInstagram(Store::factory()->create(), [
            'username' => 'moda_shop',
            'name' => 'Moda Boutique',
            'account_type' => 'BUSINESS',
            'followers_count' => 1200,
            'media_count' => 2,
            'biography' => 'Ayollar kiyimlari · Toshkent · yetkazib berish bor',
            'captions' => [],
        ]);

        $userMessage = collect($fake->calls[0]['messages'])->firstWhere('role', 'user')['content'];
        $this->assertStringContainsString('Ayollar kiyimlari', $userMessage);
    }

    public function test_extract_shop_facts_feeds_biography_into_prompt(): void
    {
        $fake = new FakeLlmClient('[]');
        $service = new PromptGeneratorService($fake);

        $service->extractShopFacts(Store::factory()->create(), array_merge($this->insights(), [
            'biography' => 'Tel: +998901112233 · Chilonzor',
        ]));

        $userMessage = collect($fake->calls[0]['messages'])->firstWhere('role', 'user')['content'];
        $this->assertStringContainsString('+998901112233', $userMessage);
    }

    /**
     * @return array{username:?string, name:?string, account_type:?string, followers_count:?int, media_count:?int, biography:?string, captions:list<string>}
     */
    private function insights(): array
    {
        return [
            'username' => 'moda_shop',
            'name' => 'Moda Boutique',
            'account_type' => 'BUSINESS',
            'followers_count' => 1200,
            'media_count' => 3,
            'biography' => null,
            'captions' => ['Yangi ko\'ylaklar keldi!', 'Toshkent bo\'ylab bepul yetkazib berish'],
        ];
    }
}
