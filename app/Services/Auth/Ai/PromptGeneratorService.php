<?php

namespace App\Services\Auth\Ai;

use App\Models\Store;
use App\Services\Llm\Contracts\LlmClient;

class PromptGeneratorService
{
    /**
     * Marker used to detect the guardrails block (also asserted in tests).
     */
    public const GUARDRAIL_MARKER = '### MAJBURIY QOIDALAR (o\'zgartirilmaydi)';

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * Generate a production system prompt from the owner's structured wishes.
     * The non-negotiable guardrails are ALWAYS appended, regardless of input,
     * so the owner cannot remove the safety rules (CLAUDE.md rule #1).
     *
     * @param  array<string, mixed>  $inputs
     */
    public function generate(Store $store, array $inputs): string
    {
        $metaPrompt = $this->buildMetaPrompt($store, $inputs);

        $generated = trim($this->llm->chat(
            config('chatig.llm.models.prompt_generator'),
            [
                ['role' => 'system', 'content' => 'You are an expert prompt engineer who writes production system prompts for sales chatbots. Always answer in Uzbek (Latin script).'],
                ['role' => 'user', 'content' => $metaPrompt],
            ],
            ['temperature' => 0.5],
        ));

        return $generated."\n\n".$this->guardrails();
    }

    private function buildMetaPrompt(Store $store, array $inputs): string
    {
        $tone = $inputs['tone'] ?? 'dostona';
        $outOfStock = $inputs['out_of_stock_behavior'] ?? '';
        $haggling = $inputs['haggling_policy'] ?? '';
        $afterHours = $inputs['after_hours_behavior'] ?? '';
        $extra = $inputs['extra_notes'] ?? '';

        return <<<PROMPT
        "{$store->name}" nomli do'kon ({$store->business_type}) uchun sotuvchi AI agentning tizim prompti (system prompt)ni yoz.
        Mijozlar o'zbek va rus tillarini aralash ishlatishadi — agent mijoz tilida javob bersin.

        Egasining xohishlari:
        - Muloqot ohangi: {$tone}
        - Tovar tugaganda: {$outOfStock}
        - Savdolashishga munosabat: {$haggling}
        - Ish vaqtidan tashqarida: {$afterHours}
        - Qo'shimcha: {$extra}

        Prompt aniq, amaliy va o'zbekcha bo'lsin. Faqat tayyor promptning o'zini qaytar, izohsiz.
        PROMPT;
    }

    private function guardrails(): string
    {
        $marker = self::GUARDRAIL_MARKER;

        return <<<GUARD
        {$marker}
        - Narx, mavjudlik va tovar ma'lumotlarini HECH QACHON o'zingdan to'qib chiqarma. Faqat `search_inventory` va boshqa tool natijalaridagi ma'lumotni ayt.
        - Narxni o'zgartirma va chegirma va'da qilma — buning uchun ruxsat yo'q.
        - Mijoz sotib olishga qiziqsa yoki ism/telefon bersa, darhol `save_lead` tool'ini chaqir.
        - Javobni bilmasang yoki ishonchsiz bo'lsang, `escalate_to_human` orqali sotuvchiga uzat.
        GUARD;
    }
}
