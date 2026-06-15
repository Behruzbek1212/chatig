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
}
