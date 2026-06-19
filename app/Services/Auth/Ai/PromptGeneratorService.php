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

    /**
     * Generate a starter system prompt straight from an Instagram profile
     * snapshot (profile fields + recent post captions). Uses the cheaper intent
     * model — a best-effort first draft the owner refines later. Guardrails are
     * always appended (CLAUDE.md rule #1).
     *
     * @param  array{username:?string, name?:?string, account_type?:?string, followers_count?:?int, follows_count?:?int, media_count?:?int, profile_picture_url?:?string, biography?:?string, captions:list<string>}  $insights
     */
    public function generateFromInstagram(Store $store, array $insights): string
    {
        $metaPrompt = $this->buildInstagramMetaPrompt($store, $insights);

        $generated = trim($this->llm->chat(
            config('chatig.llm.models.intent'), // arzon model — boshlang'ich qoralama
            [
                ['role' => 'system', 'content' => 'You are an expert prompt engineer who writes production system prompts for sales chatbots. Always answer in Uzbek (Latin script).'],
                ['role' => 'user', 'content' => $metaPrompt],
            ],
            ['temperature' => 0.5],
        ));

        return $generated."\n\n".$this->guardrails();
    }

    /**
     * Extract structured shop facts from an Instagram profile snapshot so the
     * SalesAgent can answer "what do you sell / where / delivery?" from real
     * embedded knowledge instead of guessing. Returns a list of {label, value}
     * facts the caller persists (and embeds) as ShopFacts. Best-effort: any
     * parse/LLM failure yields an empty list so onboarding never hard-fails.
     *
     * @param  array{username:?string, name?:?string, account_type?:?string, followers_count?:?int, follows_count?:?int, media_count?:?int, profile_picture_url?:?string, biography?:?string, captions:list<string>}  $insights
     * @return list<array{label:string, value:string}>
     */
    public function extractShopFacts(Store $store, array $insights): array
    {
        $metaPrompt = $this->buildFactsMetaPrompt($store, $insights);

        $raw = trim($this->llm->chat(
            config('chatig.llm.models.intent'), // arzon model — strukturali ajratib olish
            [
                ['role' => 'system', 'content' => 'You extract structured shop knowledge from an Instagram profile and return STRICT JSON only. Always write values in Uzbek (Latin script).'],
                ['role' => 'user', 'content' => $metaPrompt],
            ],
            ['temperature' => 0.2],
        ));

        return $this->parseFacts($raw);
    }

    /**
     * Parse the LLM's JSON reply into clean {label, value} facts. Tolerates a
     * ```json fence and silently drops malformed entries.
     *
     * @return list<array{label:string, value:string}>
     */
    private function parseFacts(string $raw): array
    {
        // Strip a possible ```json … ``` fence.
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw);

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        // Accept either a bare list or a {"facts": [...]} envelope.
        $items = array_is_list($decoded) ? $decoded : ($decoded['facts'] ?? []);
        if (! is_array($items)) {
            return [];
        }

        $facts = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $facts[] = [
                'label' => mb_substr($label, 0, 120),
                'value' => mb_substr($value, 0, 1000),
            ];
        }

        return $facts;
    }

    /**
     * @param  array{username:?string, name?:?string, account_type?:?string, followers_count?:?int, follows_count?:?int, media_count?:?int, profile_picture_url?:?string, biography?:?string, captions:list<string>}  $insights
     */
    private function buildFactsMetaPrompt(Store $store, array $insights): string
    {
        $name = trim((string) ($insights['name'] ?? '')) ?: '(nomsiz)';
        $username = $insights['username'] ?? '(noma\'lum)';
        $bio = trim((string) ($insights['biography'] ?? '')) ?: '(bio topilmadi)';

        $captions = array_slice($insights['captions'], 0, 12);
        $captionBlock = $captions === []
            ? '(postlar topilmadi)'
            : implode("\n---\n", array_map(fn (string $c): string => mb_substr($c, 0, 400), $captions));

        return <<<PROMPT
        "{$store->name}" nomli do'kon ({$store->business_type}) Instagram profilidan do'kon haqidagi FAKTlarni ajratib ol.
        Bu faktlar keyinchalik AI sotuvchi mijoz savollariga javob berishda ishlatiladi (manzil, telefon, yetkazib berish, ish vaqti, qaytarish siyosati, qanday mahsulotlar sotilishi va h.k.).

        Instagram:
        - Ism: {$name}
        - Username: @{$username}
        - Bio (profil tavsifi): {$bio}

        So'nggi postlar (caption'lar):
        {$captionBlock}

        Yo'riqnoma:
        - FAQAT bio, caption va profil ma'lumotlaridan kelib chiqib ANIQ ko'rinib turgan faktlarni yoz. Hech narsani o'zingdan to'qib chiqarma.
        - Bio ko'pincha eng muhim manba — manzil, telefon, yetkazib berish va mahsulot turi ko'pincha shu yerda bo'ladi.
        - Har bir fakt qisqa "label" (sarlavha) va "value" (qiymat) bo'lsin. Masalan: {"label": "Yetkazib berish", "value": "Toshkent bo'ylab bepul yetkazib berish"}.
        - Telefon, manzil, ish vaqti, narx oralig'i, mahsulot turlari, aksiya/chegirma, qaytarish siyosati kabi narsalarni qidiruv natijasida topsang yoz.
        - Agar hech qanday aniq fakt topilmasa, bo'sh ro'yxat qaytar.
        - FAQAT JSON qaytar, izohsiz. Format: {"facts": [{"label": "...", "value": "..."}]}
        PROMPT;
    }

    /**
     * @param  array{username:?string, name?:?string, account_type?:?string, followers_count?:?int, follows_count?:?int, media_count?:?int, profile_picture_url?:?string, biography?:?string, captions:list<string>}  $insights
     */
    private function buildInstagramMetaPrompt(Store $store, array $insights): string
    {
        $name = trim((string) ($insights['name'] ?? '')) ?: '(nomsiz)';
        $username = $insights['username'] ?? '(noma\'lum)';
        $accountType = $insights['account_type'] ?? '(noma\'lum)';
        $followers = $insights['followers_count'] ?? 0;
        $follows = $insights['follows_count'] ?? 0;
        $mediaCount = $insights['media_count'] ?? 0;
        $bio = trim((string) ($insights['biography'] ?? '')) ?: '(bio topilmadi)';

        // Faqat eng so'nggi caption'lardan namuna — token tejash uchun cheklaymiz.
        $captions = array_slice($insights['captions'], 0, 12);
        $captionBlock = $captions === []
            ? '(postlar topilmadi — do\'kon nomi va turidan kelib chiqib yoz)'
            : implode("\n---\n", array_map(fn (string $c): string => mb_substr($c, 0, 400), $captions));

        return <<<PROMPT
        "{$store->name}" nomli do'kon ({$store->business_type}) uchun sotuvchi AI agentning tizim prompti (system prompt)ni yoz.
        Quyida do'konning Instagram profili ma'lumotlari berilgan — shulardan kelib chiqib do'kon nima sotishini, ohangini va uslubini aniqla.

        Instagram profili:
        - Ism: {$name}
        - Username: @{$username}
        - Akkaunt turi: {$accountType}
        - Obunachilar: {$followers}
        - Obuna bo'lganlar: {$follows}
        - Postlar soni: {$mediaCount}
        - Bio (profil tavsifi): {$bio}

        So'nggi postlar (caption'lar):
        {$captionBlock}

        Yo'riqnoma:
        - Do'kon nima sotishini va mijozlar ohangini mavjud ma'lumotlardan aniqla. Post bo'lmasa, do'kon nomi va turiga tayan.
        - Mijozlar o'zbek va rus tillarini aralash ishlatadi — agent mijoz tilida javob bersin.
        - Prompt aniq, amaliy va o'zbekcha bo'lsin. Faqat tayyor promptning o'zini qaytar, izohsiz.
        PROMPT;
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
