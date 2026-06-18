<?php

namespace Tests\Feature\MiniApp;

/**
 * Builds a valid Telegram WebApp initData string signed exactly like the
 * VerifyTelegramInitData middleware verifies it, so Mini App tests never need
 * a real Telegram client.
 */
trait BuildsInitData
{
    protected string $botToken = 'test-bot-token:123';

    protected function setUpInitData(): void
    {
        config()->set('chatig.telegram.mini_app_bot_token', $this->botToken);
        config()->set('chatig.telegram.bot_username', 'ChatigCatalogBot');
    }

    /**
     * @param  array<string, mixed>  $overrides  user/start_param/auth_date overrides
     */
    protected function initData(array $overrides = []): string
    {
        $user = $overrides['user'] ?? ['id' => 777001, 'first_name' => 'Ali', 'username' => 'ali'];

        $pairs = array_filter([
            'auth_date' => (string) ($overrides['auth_date'] ?? time()),
            'query_id' => 'AAH'.str_repeat('x', 10),
            'start_param' => $overrides['start_param'] ?? null,
            'user' => json_encode($user, JSON_UNESCAPED_UNICODE),
        ], fn ($v) => $v !== null);

        ksort($pairs);
        $dataCheckString = collect($pairs)->map(fn ($v, $k) => "$k=$v")->implode("\n");

        // Sign exactly like Telegram: secret = HMAC(data="WebAppData", key=token).
        // hash_hmac($algo, $data, $key) -> token is the KEY (3rd arg).
        $secretKey = hash_hmac('sha256', 'WebAppData', $this->botToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $pairs['hash'] = $hash;

        return http_build_query($pairs);
    }

    /** @return array<string, string> */
    protected function initDataHeader(array $overrides = []): array
    {
        return ['X-Telegram-Init-Data' => $this->initData($overrides)];
    }
}
