<?php

namespace App\Services\Channels;

use App\Models\Channel;
use App\Models\Store;
use App\Services\Channels\Exceptions\InstagramException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Instagram API with Instagram Login (https://developers.facebook.com/docs/instagram-platform).
 *
 * Users authenticate directly with their Instagram Business/Creator account —
 * no Facebook Page. OAuth: instagram.com/oauth/authorize -> api.instagram.com
 * (short-lived token) -> graph.instagram.com (long-lived token). All resource
 * calls use the graph.instagram.com host.
 */
class InstagramService
{
    private function graph(): string
    {
        return 'https://graph.instagram.com/'.config('chatig.instagram.graph_version');
    }

    /**
     * Build the Instagram authorization URL. State carries the (encrypted)
     * store id so the callback can attribute the connection without a session.
     */
    public function connectUrl(Store $store): string
    {
        $appId = config('chatig.instagram.app_id');
        $redirectUri = config('chatig.instagram.redirect_uri');

        if (! is_string($appId) || $appId === '' || ! is_string($redirectUri) || $redirectUri === '') {
            throw new InstagramException(
                'Instagram integratsiyasi sozlanmagan. INSTAGRAM_APP_ID va INSTAGRAM_REDIRECT_URI ni .env faylida to\'ldiring.',
            );
        }

        $params = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', config('chatig.instagram.scopes')),
            'state' => Crypt::encryptString((string) $store->id),
        ]);

        return 'https://www.instagram.com/oauth/authorize?'.$params;
    }

    public function storeIdFromState(string $state): int
    {
        try {
            return (int) Crypt::decryptString($state);
        } catch (DecryptException) {
            throw new InstagramException('Yaroqsiz state parametri.');
        }
    }

    /**
     * Complete the OAuth flow for the store encoded in $state and persist a
     * connected Instagram channel.
     */
    public function handleCallback(string $code, string $state): Channel
    {
        $store = Store::findOrFail($this->storeIdFromState($state));

        $short = $this->exchangeCodeForToken($code);
        $longLived = $this->exchangeForLongLivedToken($short['access_token']);
        $profile = $this->fetchProfile($longLived['access_token']);

        $this->subscribeToMessages($longLived['access_token']);

        return Channel::updateOrCreate(
            ['store_id' => $store->id, 'type' => 'instagram'],
            [
                'external_id' => (string) ($profile['user_id'] ?? $short['user_id']),
                'username' => $profile['username'] ?? null,
                'access_token' => $longLived['access_token'],
                'status' => 'connected',
                'token_expires_at' => now()->addSeconds((int) ($longLived['expires_in'] ?? 5184000)),
                'meta' => ['scopes' => config('chatig.instagram.scopes')],
            ],
        );
    }

    public function disconnect(Channel $channel): void
    {
        $channel->update(['status' => 'disconnected', 'access_token' => null]);
    }

    /**
     * Send a text direct message to a customer (recipient = their IGSID).
     */
    public function sendMessage(Channel $channel, string $recipientId, string $text): void
    {
        $response = Http::withToken($channel->access_token)
            ->post($this->graph().'/me/messages', [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $text],
            ]);

        if ($response->failed()) {
            throw new InstagramException('Instagram xabar yuborishda xato: '.$response->body());
        }
    }

    /**
     * @return array{access_token:string, user_id:mixed}
     */
    private function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => config('chatig.instagram.app_id'),
            'client_secret' => config('chatig.instagram.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('chatig.instagram.redirect_uri'),
            'code' => $code,
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new InstagramException('Kodni tokenga almashtirib bo\'lmadi.');
        }

        return [
            'access_token' => $response->json('access_token'),
            'user_id' => $response->json('user_id'),
        ];
    }

    /**
     * @return array{access_token:string, expires_in:mixed}
     */
    private function exchangeForLongLivedToken(string $shortToken): array
    {
        $response = Http::get($this->graph().'/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('chatig.instagram.app_secret'),
            'access_token' => $shortToken,
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new InstagramException('Uzoq muddatli tokenni olishda xato.');
        }

        return [
            'access_token' => $response->json('access_token'),
            'expires_in' => $response->json('expires_in'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProfile(string $token): array
    {
        $response = Http::get($this->graph().'/me', [
            'fields' => 'user_id,username',
            'access_token' => $token,
        ]);

        if ($response->failed()) {
            throw new InstagramException('Instagram profilini olishda xato.');
        }

        return $response->json();
    }

    private function subscribeToMessages(string $token): void
    {
        $response = Http::post($this->graph().'/me/subscribed_apps', [
            'subscribed_fields' => 'messages',
            'access_token' => $token,
        ]);

        if ($response->failed()) {
            throw new InstagramException('Webhook obunasida xato.');
        }
    }
}
