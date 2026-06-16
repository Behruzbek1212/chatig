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

        $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);
        $expected = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($expected, $hash)) {
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
}
