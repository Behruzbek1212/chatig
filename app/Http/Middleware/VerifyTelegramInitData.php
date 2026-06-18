<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies a Telegram Mini App (WebApp) initData string sent in the
 * X-Telegram-Init-Data header. Per Telegram's spec:
 *   secret_key   = HMAC_SHA256("WebAppData", bot_token)
 *   data_check   = the initData pairs (except `hash`) sorted by key, joined "k=v\n"
 *   valid        = hash_equals(hash, HMAC_SHA256(data_check, secret_key))
 * Also rejects stale payloads (auth_date older than init_data_max_age) to
 * limit replay. On success it stashes the parsed user + start_param on the
 * request for downstream middleware/controllers.
 */
class VerifyTelegramInitData
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('chatig.telegram.mini_app_bot_token');
        $initData = (string) $request->header('X-Telegram-Init-Data', '');

        // Local dev bypass: skip HMAC verification when no initData is sent
        // and the app is running in local environment. Injects a fake TG user
        // so downstream code (ResolveStoreFromPublicId, controllers) works.
        if ($initData === '' && app()->environment('local')) {
            $request->attributes->set('tg_user', [
                'id' => 0,
                'first_name' => 'Dev',
                'username' => 'dev_user',
            ]);
            $request->attributes->set('tg_start_param', '');
            $request->attributes->set('tg_init_data', '');

            return $next($request);
        }

        if ($token === '' || $initData === '') {
            abort(403, 'Invalid init data.');
        }

        parse_str($initData, $pairs);

        $hash = (string) ($pairs['hash'] ?? '');
        unset($pairs['hash']);

        if ($hash === '') {
            abort(403, 'Invalid init data.');
        }

        ksort($pairs);
        $dataCheckString = collect($pairs)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        // Per Telegram spec the secret key is HMAC over the literal string
        // "WebAppData" using the bot token as the KEY — i.e. data="WebAppData",
        // key=$token. hash_hmac()'s signature is ($algo, $data, $key), so the
        // token must be the 3rd argument, not the 2nd.
        $secretKey = hash_hmac('sha256', 'WebAppData', $token, true);
        $expected = hash_hmac('sha256', $dataCheckString, $secretKey);

        // Primary check: HMAC over the bot token (Telegram's classic scheme,
        // also what our test suite signs with). Real Telegram clients (e.g.
        // tdesktop) additionally send an Ed25519 `signature`; some payloads do
        // not validate via HMAC but do via that signature, so fall back to it.
        if (! hash_equals($expected, $hash) && ! $this->signatureValid($pairs, $token)) {
            abort(403, 'Invalid init data.');
        }

        $authDate = (int) ($pairs['auth_date'] ?? 0);
        $maxAge = (int) config('chatig.telegram.init_data_max_age');
        if ($authDate <= 0 || ($maxAge > 0 && (time() - $authDate) > $maxAge)) {
            abort(403, 'Init data expired.');
        }

        $user = isset($pairs['user']) ? json_decode((string) $pairs['user'], true) : null;
        if (! is_array($user) || ! isset($user['id'])) {
            abort(403, 'Invalid init data.');
        }

        $request->attributes->set('tg_user', $user);
        $request->attributes->set('tg_start_param', (string) ($pairs['start_param'] ?? ''));
        $request->attributes->set('tg_init_data', $initData);

        return $next($request);
    }

    /**
     * Telegram's token-independent initData verification (third-party scheme):
     *   data_check = "{bot_id}:WebAppData\n" + fields (except hash & signature),
     *                sorted by key, joined with "\n"
     *   valid      = Ed25519_verify(signature, data_check, telegram_public_key)
     * See https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     *
     * @param  array<string, string>  $pairs  parsed initData pairs (hash already removed)
     */
    private function signatureValid(array $pairs, string $token): bool
    {
        $signature = (string) ($pairs['signature'] ?? '');
        $publicKey = (string) config('chatig.telegram.init_data_public_key');
        if ($signature === '' || $publicKey === '' || ! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $botId = explode(':', $token, 2)[0];

        $fields = $pairs;
        unset($fields['signature']);
        ksort($fields);
        $dataCheckString = $botId.':WebAppData'."\n".collect($fields)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        // signature is base64url; pad and translate to standard base64 before decode.
        $b64 = strtr($signature, '-_', '+/');
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $signatureBin = base64_decode($b64, true);
        $publicKeyBin = @hex2bin($publicKey);
        if ($signatureBin === false || $publicKeyBin === false || strlen($publicKeyBin) !== 32) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signatureBin, $dataCheckString, $publicKeyBin);
    }
}
