<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Store;
use App\Services\Auth\Ai\AiSettingsService;
use App\Services\Auth\Ai\PromptGeneratorService;
use App\Services\Channels\InstagramService;
use App\Services\ShopFacts\ShopFactService;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Right after Instagram connects, read the account's profile + recent post
 * captions and (1) generate a starter AI system prompt tailored to the shop and
 * (2) extract structured shop facts (delivery, hours, product types, …) which
 * are persisted as ShopFacts and embedded — so the SalesAgent answers from real
 * knowledge from minute one instead of a blank agent. Best-effort: failures
 * mark the channel `failed` but never block onboarding (the owner can still set
 * up the prompt and facts manually).
 */
class AnalyzeInstagramProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $channelId) {}

    public function handle(
        InstagramService $instagram,
        PromptGeneratorService $prompts,
        AiSettingsService $settings,
        ShopFactService $shopFacts,
        Tenancy $tenancy,
    ): void {
        // Read across the global store scope (job has no auth user), then bind
        // the tenant so AiConfig::create auto-fills the right store_id.
        $channel = Channel::withoutGlobalScope('store')->find($this->channelId);

        if (! $channel || $channel->type !== 'instagram' || ! $channel->access_token) {
            return;
        }

        $store = Store::find($channel->store_id);
        if (! $store) {
            return;
        }

        $tenancy->set($store);

        try {
            // Skip if the owner already has an AI config (e.g. re-connect).
            if ($settings->current($store) !== null) {
                $this->markStatus($channel, 'ready', 'ready');

                return;
            }

            // Har bosqichni DB'ga yozamiz — front real vaqtda poll qilib ko'rsatadi.
            $this->markStatus($channel, 'pending', 'reading_profile');
            $insights = $instagram->fetchProfileInsights($channel);

            $this->markStatus($channel, 'pending', 'analyzing_posts');

            $this->markStatus($channel, 'pending', 'generating_prompt');
            $prompt = $prompts->generateFromInstagram($store, $insights);

            $this->markStatus($channel, 'pending', 'saving');
            $settings->save($store, [
                'system_prompt' => $prompt,
                'mode' => 'suggest',
                'raw_inputs' => [
                    'source' => 'instagram_auto',
                    'username' => $insights['username'],
                    'account_type' => $insights['account_type'] ?? null,
                    'followers_count' => $insights['followers_count'] ?? null,
                    'follows_count' => $insights['follows_count'] ?? null,
                    'media_count' => $insights['media_count'] ?? null,
                    'profile_picture_url' => $insights['profile_picture_url'] ?? null,
                    'has_bio' => ! empty($insights['biography']),
                    'caption_count' => count($insights['captions']),
                ],
            ]);

            // Do'kon faktlarini ajratib olib, embedding bilan saqlaymiz — shu
            // bilan SalesAgent search_shop_info orqali real bilimdan javob beradi.
            $this->markStatus($channel, 'pending', 'embedding_facts');
            $this->bootstrapShopFacts($store, $insights, $prompts, $shopFacts);

            $this->markStatus($channel, 'ready', 'ready');
        } catch (Throwable $e) {
            Log::warning('Instagram profile analysis failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);
            $this->markStatus($channel, 'failed', 'failed');
        }
    }

    /**
     * Extract structured shop facts from the IG profile and persist them via
     * ShopFactService (which embeds each one). Skips entirely if the store
     * already has facts — so a re-connect never duplicates them. Non-fatal: a
     * fact-extraction failure must not fail the whole onboarding (the prompt is
     * already saved by this point).
     *
     * @param  array{username:?string, name:?string, account_type:?string, followers_count:?int, follows_count:?int, media_count:?int, profile_picture_url:?string, biography:?string, captions:list<string>}  $insights
     */
    private function bootstrapShopFacts(
        Store $store,
        array $insights,
        PromptGeneratorService $prompts,
        ShopFactService $shopFacts,
    ): void {
        // Egasi allaqachon fakt kiritgan bo'lsa, avtomatik to'ldirmaymiz.
        if ($shopFacts->list($store)->isNotEmpty()) {
            return;
        }

        try {
            $facts = $prompts->extractShopFacts($store, $insights);
        } catch (Throwable $e) {
            Log::warning('Instagram shop-fact extraction failed', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($facts as $order => $fact) {
            $shopFacts->create($store, [
                'label' => $fact['label'],
                'value' => $fact['value'],
                'display_order' => $order,
            ]);
        }
    }

    /**
     * Persist overall status + the current granular step so the SPA can render
     * a live, stage-by-stage progress view.
     */
    private function markStatus(Channel $channel, string $status, string $step): void
    {
        $meta = $channel->meta ?? [];
        $meta['ai_setup_status'] = $status;
        $meta['ai_setup_step'] = $step;
        $channel->forceFill(['meta' => $meta])->save();
    }
}
