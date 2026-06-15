<?php

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Eskiz.uz SMS gateway (https://eskiz.uz). Stub wiring — credentials come from
 * config/chatig.php (.env). Token is fetched and cached; swap to a real
 * account when going live.
 */
class EskizSmsSender implements SmsSender
{
    /** @param array{base_url:string,email:?string,password:?string,from:string} $config */
    public function __construct(private readonly array $config) {}

    public function send(string $phone, string $message): void
    {
        $token = $this->token();

        $response = Http::withToken($token)
            ->asMultipart()
            ->post($this->config['base_url'].'/message/sms/send', [
                ['name' => 'mobile_phone', 'contents' => ltrim($phone, '+')],
                ['name' => 'message', 'contents' => $message],
                ['name' => 'from', 'contents' => $this->config['from']],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Eskiz SMS send failed: '.$response->body());
        }
    }

    private function token(): string
    {
        return Cache::remember('eskiz.token', now()->addDays(25), function (): string {
            $response = Http::asForm()->post($this->config['base_url'].'/auth/login', [
                'email' => $this->config['email'],
                'password' => $this->config['password'],
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Eskiz auth failed: '.$response->body());
            }

            return $response->json('data.token');
        });
    }
}
