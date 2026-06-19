<?php

namespace App\Services\Channels;

use App\Jobs\AnalyzeInstagramProfile;
use App\Models\AiConfig;
use App\Models\Channel;
use App\Models\ShopFact;
use App\Models\Store;
use App\Services\Channels\Exceptions\InstagramException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

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

        $channel = Channel::updateOrCreate(
            ['store_id' => $store->id, 'type' => 'instagram'],
            [
                'external_id' => (string) ($profile['user_id'] ?? $short['user_id']),
                'username' => $profile['username'] ?? null,
                'access_token' => $longLived['access_token'],
                'status' => 'connected',
                'token_expires_at' => now()->addSeconds((int) ($longLived['expires_in'] ?? 5184000)),
                'meta' => [
                    'scopes' => config('chatig.instagram.scopes'),
                    'ai_setup_status' => 'pending',
                ],
            ],
        );

        // Analyse the freshly-connected profile and generate a starter AI prompt.
        AnalyzeInstagramProfile::dispatch($channel->id);

        return $channel;
    }

    /**
     * Disconnect the channel AND clear the auto-generated AI setup (system prompt
     * + bootstrapped shop facts) for its store. This makes a later re-connect run
     * the full AnalyzeInstagramProfile pipeline again from scratch — re-reading
     * the profile/bio/posts and regenerating the prompt + facts — instead of the
     * job short-circuiting on a pre-existing AiConfig. Scoped explicitly to the
     * channel's store so it never touches another tenant.
     */
    public function disconnect(Channel $channel): void
    {
        $channel->update(['status' => 'disconnected', 'access_token' => null]);

        AiConfig::withoutGlobalScope('store')->where('store_id', $channel->store_id)->delete();
        ShopFact::withoutGlobalScope('store')->where('store_id', $channel->store_id)->delete();
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

    /**
     * Fetch a lightweight snapshot of the connected account for AI analysis:
     * the profile fields the IG Login API exposes (name, account_type, counts,
     * profile picture) plus the most recent media captions and — best-effort —
     * the bio (see fetchBiography()). NOTE: `biography` is NOT on the plain
     * Instagram-Login `/me` edge, so we read it via the Business Discovery edge
     * and silently fall back to captions-only when it is unavailable.
     * Best-effort throughout — any failure returns whatever was gathered so
     * onboarding never hard-fails.
     *
     * @return array{username:?string, name:?string, account_type:?string, followers_count:?int, follows_count:?int, media_count:?int, profile_picture_url:?string, biography:?string, captions:list<string>}
     */
    public function fetchProfileInsights(Channel $channel, int $mediaLimit = 15): array
    {
        $token = $channel->access_token;

        $username = $channel->username;
        $name = null;
        $accountType = null;
        $followers = null;
        $follows = null;
        $mediaCount = null;
        $profilePictureUrl = null;
        try {
            $profile = Http::get($this->graph().'/me', [
                'fields' => 'username,name,account_type,followers_count,follows_count,media_count,profile_picture_url',
                'access_token' => $token,
            ]);
            if ($profile->successful()) {
                $username = $profile->json('username') ?? $username;
                $name = $profile->json('name');
                $accountType = $profile->json('account_type');
                $followers = $profile->json('followers_count');
                $follows = $profile->json('follows_count');
                $mediaCount = $profile->json('media_count');
                $profilePictureUrl = $profile->json('profile_picture_url');
            }
        } catch (Throwable) {
            // ignore — profile context is optional
        }

        // Bio is the single richest signal about what the shop sells/offers, but
        // the Instagram-Login API hides it on /me. Pull it via Business Discovery
        // (best-effort; null when the account is personal/private or the edge is
        // unsupported).
        $biography = $username !== null ? $this->fetchBiography($token, $username) : null;

        $captions = [];
        try {
            $media = Http::get($this->graph().'/me/media', [
                'fields' => 'caption,media_type',
                'limit' => $mediaLimit,
                'access_token' => $token,
            ]);
            if ($media->successful()) {
                foreach ($media->json('data', []) as $item) {
                    $caption = trim((string) ($item['caption'] ?? ''));
                    if ($caption !== '') {
                        $captions[] = $caption;
                    }
                }
            }
        } catch (Throwable) {
            // ignore — captions are optional context
        }

        return [
            'username' => $username,
            'name' => $name,
            'account_type' => $accountType,
            'followers_count' => $followers !== null ? (int) $followers : null,
            'follows_count' => $follows !== null ? (int) $follows : null,
            'media_count' => $mediaCount !== null ? (int) $mediaCount : null,
            'profile_picture_url' => $profilePictureUrl !== null ? (string) $profilePictureUrl : null,
            'biography' => $biography,
            'captions' => $captions,
        ];
    }

    /**
     * Read the account's own bio via the Business Discovery edge — the only way
     * to obtain `biography` under the Instagram-Login API. Self-discovery (an
     * account looking itself up by username) works for public Business/Creator
     * accounts. Strictly best-effort: returns null on any failure (personal/
     * private account, unsupported edge, network error) so the caller falls
     * back to caption-only context.
     */
    private function fetchBiography(string $token, string $username): ?string
    {
        try {
            $response = Http::get($this->graph().'/me', [
                'fields' => "business_discovery.username({$username}){biography}",
                'access_token' => $token,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $bio = trim((string) $response->json('business_discovery.biography', ''));

            return $bio !== '' ? $bio : null;
        } catch (Throwable) {
            return null;
        }
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
