<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use App\Services\Channels\Exceptions\InstagramException;
use App\Services\Channels\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IntegrationController extends ApiController
{
    public function __construct(private readonly InstagramService $instagram) {}

    public function index(): AnonymousResourceCollection
    {
        return ChannelResource::collection(Channel::query()->latest()->get());
    }

    public function connectUrl(Request $request): JsonResponse
    {
        return $this->ok([
            'url' => $this->instagram->connectUrl($request->user()->store),
        ]);
    }

    /**
     * Lightweight, pollable status of the store's Instagram channel and its AI
     * setup pipeline. The SPA polls this after the OAuth popup closes to render
     * live progress (reading_profile → … → ready/failed). Store-scoped: the
     * channel is resolved through the global store scope, never client input.
     */
    public function instagramStatus(): JsonResponse
    {
        $channel = Channel::query()->where('type', 'instagram')->first();

        if (! $channel) {
            return $this->ok([
                'connected' => false,
                'ai_setup_status' => null,
                'ai_setup_step' => null,
            ]);
        }

        return $this->ok(new ChannelResource($channel));
    }

    /**
     * Browser redirect endpoint — opens Instagram OAuth directly in a popup.
     * No JSON: the popup window navigates here and gets redirected to Instagram.
     */
    public function auth(Request $request): RedirectResponse
    {
        $url = $this->instagram->connectUrl($request->user()->store);

        return redirect()->away($url);
    }

    /**
     * Public endpoint hit by Instagram's browser redirect. Resolves the store
     * from the signed state, then redirects back to the SPA with status.
     */
    public function callback(Request $request): RedirectResponse
    {
        $spa = rtrim(config('chatig.spa_url'), '/').'/integrations';

        if ($request->filled('error') || ! $request->filled('code') || ! $request->filled('state')) {
            return redirect()->away($spa.'?instagram=error');
        }

        try {
            $this->instagram->handleCallback($request->string('code')->value(), $request->string('state')->value());
        } catch (InstagramException) {
            return redirect()->away($spa.'?instagram=error');
        }

        return redirect()->away($spa.'?instagram=connected');
    }

    public function destroy(Channel $channel): JsonResponse
    {
        $this->instagram->disconnect($channel);

        return $this->ok(new ChannelResource($channel->refresh()));
    }

    /**
     * Telegram catalog uses ONE shared platform bot — no token to paste. This
     * marks the store's telegram channel as connected and returns the deep link
     * the owner shares so customers open the Mini App for their store.
     */
    public function telegramConnect(Request $request): JsonResponse
    {
        $store = $request->user()->store;

        $channel = Channel::updateOrCreate(
            ['store_id' => $store->id, 'type' => 'telegram'],
            [
                'status' => 'connected',
                'username' => config('chatig.telegram.bot_username'),
                'meta' => ['mini_app' => true],
            ],
        );

        return $this->ok([
            'channel' => new ChannelResource($channel),
            'deep_link' => $this->telegramDeepLink($store->public_id),
        ]);
    }

    private function telegramDeepLink(string $publicId): ?string
    {
        $username = config('chatig.telegram.bot_username');

        return $username ? sprintf('https://t.me/%s/app?startapp=%s', $username, $publicId) : null;
    }
}
